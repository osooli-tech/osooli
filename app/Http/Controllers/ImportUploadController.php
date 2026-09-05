<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportKind;
use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use ZipArchive;

/**
 * Chunked upload for import archives.
 *
 * The browser sends 2 MB chunks so a large archive fits inside the host's
 * post_max_size without a server configuration change. Chunks must arrive in
 * order; an out-of-order chunk is answered with the index the server actually
 * wants, so a dropped connection resumes instead of corrupting the file.
 *
 * Every request in this controller is treated as hostile: the {uuid} route
 * parameter is attacker-controlled, so every batch lookup is scoped to the
 * authenticated owner (ownedBatch()) — a batch that does not exist and one
 * owned by someone else are both reported as 404, matching how this app's
 * mobile API already collapses that distinction elsewhere (bootstrap.php).
 * The stored file's path is always derived from the server-generated batch
 * uuid and a validated (never client-freeform) extension, never from the
 * client-supplied original_filename, which is stored only for display. And
 * because a client decides how many chunks it sends and how big each one is,
 * chunk() verifies the running total against the batch's declared byte_size
 * before writing anything further to disk, so a malformed or malicious client
 * cannot inflate the file past what was declared (and, by construction, past
 * imports.max_upload_bytes).
 */
final class ImportUploadController extends Controller
{
    /** Bytes read from a .geojson/.json upload to sanity-check it — see looksLikeGeoJson(). */
    private const GEOJSON_SNIFF_BYTES = 8192;

    public function create(Request $request): JsonResponse
    {
        $this->authorize('imports.create');

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ImportKind::class)],
            'filename' => ['required', 'string', 'max:255'],
            'byte_size' => ['required', 'integer', 'min:1', 'max:'.(int) config('imports.max_upload_bytes')],
        ]);

        $kind = ImportKind::from($validated['kind']);
        $allowed = $kind === ImportKind::Gdb ? ['zip', 'geojson', 'json'] : ['zip'];
        $extension = strtolower(pathinfo($validated['filename'], PATHINFO_EXTENSION));

        if (! in_array($extension, $allowed, true)) {
            return response()->json([
                'message' => __('imports.errors.extension', ['allowed' => implode(', ', $allowed)]),
            ], 422);
        }

        $uuid = (string) Str::uuid();

        // $extension is one of the literals in $allowed above — never the raw
        // original_filename — so it is safe to use here, and it is the only
        // client-influenced value that ever reaches a filesystem path. The
        // stored file must keep this real extension (not a generic name):
        // GdbConverter's .geojson/.json passthrough decides whether to run
        // GDAL at all by testing this same path's suffix, so a batch that
        // loses its extension here is unprocessable no matter what it holds.
        $storedPath = Storage::disk('local')->path('imports/'.$uuid.'/source.'.$extension);

        $batch = ImportBatch::create([
            'uuid' => $uuid,
            'user_id' => $request->user()->id,
            'kind' => $kind,
            'status' => ImportStatus::Uploading,
            // Stored for display only (see class docblock) — never used to
            // build a filesystem path.
            'original_filename' => $validated['filename'],
            'byte_size' => $validated['byte_size'],
            // Set immediately, before a single byte exists on disk, so an
            // abandoned or failed upload is visible to
            // ImportBatch::scopeStale(), which filters on
            // whereNotNull('stored_path'). Leaving this null until completion
            // would hide exactly the uploads that never finish — the ones
            // most likely to leak disk space.
            'stored_path' => $storedPath,
        ]);

        return response()->json([
            'uuid' => $batch->uuid,
            'chunk_bytes' => (int) config('imports.chunk_bytes'),
        ]);
    }

    public function chunk(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        $index = $request->integer('index');
        $chunkPath = $request->file('chunk')->getRealPath();

        abort_if($chunkPath === false, 422, __('imports.errors.invalid_chunk'));

        // Measured with the same filesize() used everywhere else in this
        // class (never UploadedFile::getSize()) so the number that gates the
        // write below is the exact byte count appendChunk() will stream from
        // — no room for the two to disagree.
        $chunkSize = filesize($chunkPath);

        abort_if($chunkSize === false, 422, __('imports.errors.invalid_chunk'));

        // The read (received_chunks / current file size), the decision, and
        // the write are locked together for the lifetime of this batch row
        // so two concurrent requests for the same {uuid} (a crafted
        // double-submit, not just a dropped connection) cannot both pass the
        // same check and each append their chunk.
        return DB::transaction(function () use ($request, $uuid, $index, $chunkPath, $chunkSize): JsonResponse {
            $batch = $this->ownedBatch($request, $uuid, lockForUpdate: true);

            abort_unless($batch->status === ImportStatus::Uploading, 409, __('imports.errors.not_uploading'));

            if ($index !== $batch->received_chunks) {
                return response()->json([
                    'message' => __('imports.errors.out_of_order'),
                    'expected_index' => $batch->received_chunks,
                ], 409);
            }

            $absolute = (string) $batch->stored_path;
            $currentSize = is_file($absolute) ? (int) filesize($absolute) : 0;

            // The client's declared byte_size is the hard ceiling for what
            // this batch may ever write to disk (and byte_size itself was
            // already capped at imports.max_upload_bytes when the batch was
            // created). Without this check, a client that keeps sending
            // well-formed, correctly-indexed chunks past the declared size —
            // or a single oversized chunk — would grow the file without
            // bound.
            if ($currentSize + $chunkSize > $batch->byte_size) {
                $batch->markFailed(__('imports.errors.size_exceeded'));
                $this->deleteUpload($batch);

                return response()->json([
                    'message' => __('imports.errors.size_exceeded'),
                ], 422);
            }

            Storage::disk('local')->makeDirectory($this->uploadDirectory($batch));

            // Incremented before the chunk is appended: filesystem writes are
            // not part of this DB transaction, so if appendChunk() throws
            // (an I/O error, a full disk) this increment rolls back with
            // everything else in the transaction, and the client's retry of
            // the same index lands on a file whose length still matches what
            // the (rolled-back) counter says was received.
            $batch->increment('received_chunks');
            $this->appendChunk($absolute, $chunkPath, $chunkSize);

            return response()->json(['next_index' => $batch->received_chunks]);
        });
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        // $batch is captured by reference from inside the transaction so it
        // can be inspected once the lock below is released — dispatching the
        // (potentially slow) analyze job while still holding the row lock
        // would keep every other request against this batch blocked for no
        // reason.
        $batch = null;

        $response = DB::transaction(function () use ($request, $uuid, &$batch): JsonResponse {
            $batch = $this->ownedBatch($request, $uuid, lockForUpdate: true);

            abort_unless($batch->status === ImportStatus::Uploading, 409, __('imports.errors.not_uploading'));

            $absolute = (string) $batch->stored_path;
            $actual = is_file($absolute) ? (int) filesize($absolute) : 0;

            if ($actual !== (int) $batch->byte_size) {
                $message = __('imports.errors.size_mismatch', ['expected' => $batch->byte_size, 'actual' => $actual]);
                $batch->markFailed($message);
                $this->deleteUpload($batch);

                return response()->json(['message' => $message], 422);
            }

            $checksum = hash_file('sha256', $absolute);

            if ($checksum === false) {
                $message = __('imports.errors.invalid_archive');
                $batch->markFailed($message);
                $this->deleteUpload($batch);

                return response()->json(['message' => $message], 422);
            }

            // The only content-level check before the queued analyze job
            // runs: a .zip must actually open as an archive, and a
            // .geojson/.json must at least look like a JSON document (see
            // looksLikeGeoJson() for why this is a bounded sniff, not a
            // parse). Without this, an upload that cleared create()'s
            // extension whitelist but is not really that format fails
            // asynchronously and opaquely in the queued job instead of here,
            // synchronously, with a message the operator sees immediately.
            if (! $this->archiveIsReadable($absolute)) {
                $message = __('imports.errors.invalid_archive');
                $batch->markFailed($message);
                $this->deleteUpload($batch);

                return response()->json(['message' => $message], 422);
            }

            $transitioned = $batch->transitionTo(ImportStatus::Uploaded, [
                'stored_path' => $absolute,
                'checksum' => $checksum,
            ]);

            // False means the batch was no longer Uploading by the time this
            // write raced another request for the same {uuid} (a replayed or
            // double-submitted complete()) — do not dispatch analysis twice.
            abort_unless($transitioned, 409, __('imports.errors.not_uploading'));

            return response()->json(['uuid' => $batch->uuid, 'status' => $batch->status->value]);
        });

        if ($batch instanceof ImportBatch && $batch->status === ImportStatus::Uploaded) {
            $batch->dispatchAnalysis();
        }

        return $response;
    }

    /**
     * Loads the batch for {uuid} and enforces that it belongs to the
     * authenticated user. {uuid} is an attacker-controlled route parameter,
     * so every endpoint that accepts one must go through here rather than
     * loading the batch directly.
     */
    private function ownedBatch(Request $request, string $uuid, bool $lockForUpdate = false): ImportBatch
    {
        $this->authorize('imports.create');

        $query = ImportBatch::where('uuid', $uuid);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $batch = $query->firstOrFail();

        // A missing batch and one owned by someone else are both reported as
        // 404 — a caller must not be able to tell "does not exist" apart
        // from "exists but is not yours".
        abort_unless($batch->user_id === $request->user()->id, 404);

        return $batch;
    }

    /**
     * The on-disk directory for a batch's upload, derived only from the
     * server-generated uuid — never from the client-supplied
     * original_filename, which must not be able to influence a filesystem
     * path.
     */
    private function uploadDirectory(ImportBatch $batch): string
    {
        return 'imports/'.$batch->uuid;
    }

    /**
     * Removes a batch's uploaded bytes immediately once it has failed, so a
     * closed-tab upload is the only thing left for the retention job to find
     * — a size-exceeded or size-mismatch failure does not additionally sit
     * on disk until that job runs.
     */
    private function deleteUpload(ImportBatch $batch): void
    {
        Storage::disk('local')->deleteDirectory($this->uploadDirectory($batch));
    }

    /**
     * Confirms the assembled upload actually contains what its extension
     * claims: a .zip must open as an archive, a .geojson/.json must at least
     * look like a JSON document.
     */
    private function archiveIsReadable(string $absolute): bool
    {
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        if ($extension === 'zip') {
            $zip = new ZipArchive;
            $opened = $zip->open($absolute) === true;

            if ($opened) {
                $zip->close();
            }

            return $opened;
        }

        return $this->looksLikeGeoJson($absolute);
    }

    /**
     * A cheap, bounded sanity check — not a parse. Only the first
     * GEOJSON_SNIFF_BYTES of the file are ever read, regardless of its real
     * size (up to imports.max_upload_bytes, 512 MB by default): reading the
     * whole thing and json_decode()-ing it into a PHP array here, inside a
     * synchronous HTTP request, would risk an uncatchable OOM fatal on a
     * large but entirely legitimate GeoJSON — precisely the file this
     * whitelisted format exists to accept (GdbConverter's GDAL-less
     * passthrough).
     *
     * This deliberately does not require a "features" key to appear within
     * the window. "type" and "features" may legitimately appear in either
     * order, and a large crs/bbox block ahead of "features" is exactly the
     * kind of document most likely to both be huge and push "features" past
     * any prefix window affordable here — the biggest files are the ones a
     * "features"-in-window check would most often reject. So this only
     * confirms the upload opens as a JSON object (first non-space byte is
     * '{') and that what was read decodes as valid UTF-8. The real
     * structural validation — a genuine features array, well-formed
     * geometries — happens in the queued analyze job via
     * ParcelGeoJsonImporter::readFeatures(), where a failure is recoverable
     * instead of fataling this request.
     */
    private function looksLikeGeoJson(string $absolute): bool
    {
        $prefix = file_get_contents($absolute, false, null, 0, self::GEOJSON_SNIFF_BYTES);

        if ($prefix === false || $prefix === '') {
            return false;
        }

        // A short read means file_get_contents() hit EOF before filling the
        // sniff window — $prefix is the *entire* file, so its last bytes are
        // real content, not a truncation artifact, and must not be trimmed
        // below. Only a full-length read can possibly have been cut
        // mid-character at the window boundary (a file exactly
        // GEOJSON_SNIFF_BYTES long reads as full-length too and is
        // indistinguishable from a longer one cut at the same point — trimming
        // in that exact-length case is the conservative, correct choice).
        $mayBeTruncated = strlen($prefix) === self::GEOJSON_SNIFF_BYTES;

        // A leading UTF-8 BOM is not whitespace as far as ltrim() is
        // concerned, but GeoJSON exports occasionally carry one.
        if (str_starts_with($prefix, "\xEF\xBB\xBF")) {
            $prefix = substr($prefix, 3);
        }

        $trimmed = ltrim($prefix);

        if ($trimmed === '' || $trimmed[0] !== '{') {
            return false;
        }

        // Only drop the last 3 bytes when the read may actually have been cut
        // mid-character at the window boundary (see $mayBeTruncated above) —
        // very plausible there, since this app's GeoJSON exports carry Arabic
        // property values, and the longest UTF-8 sequence is 4 bytes, so
        // trimming 3 always clears an incomplete trailing sequence. On a short
        // read those same trailing bytes are genuine file content (e.g. a
        // closing Arabic value or an emoji right before the final "}"), and
        // trimming them would cut a real character in half instead of an
        // artifact — exactly the false rejection an earlier version of this
        // check produced.
        $safeForEncodingCheck = $mayBeTruncated && strlen($prefix) > 3
            ? substr($prefix, 0, -3)
            : $prefix;

        return mb_check_encoding($safeForEncodingCheck, 'UTF-8');
    }

    /**
     * Appends the chunk to the destination via a stream copy rather than
     * reading it into a PHP string with file_get_contents(): the caller has
     * already bounded the chunk against the batch's remaining allowance, but
     * streaming keeps memory use flat regardless of chunk size.
     *
     * stream_copy_to_stream()'s and fclose()'s return values are checked
     * rather than trusted: on a full disk or another I/O error, a short or
     * failed write would otherwise leave received_chunks claiming more bytes
     * landed than actually did, silently corrupting the file.
     */
    private function appendChunk(string $destination, string $source, int $expectedBytes): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'ab');

        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }

            throw new RuntimeException('Unable to open upload chunk stream.');
        }

        $copied = stream_copy_to_stream($in, $out);
        $sourceClosed = fclose($in);
        $destClosed = fclose($out);

        if ($copied !== $expectedBytes || ! $sourceClosed || ! $destClosed) {
            throw new RuntimeException('Failed to write the full upload chunk to disk.');
        }
    }
}
