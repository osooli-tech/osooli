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
 * authenticated owner (ownedBatch()). The stored file's path is always
 * derived from the server-generated batch uuid, never from the client-supplied
 * original_filename, which is stored only for display. And because a client
 * decides how many chunks it sends and how big each one is, chunk() verifies
 * the running total against the batch's declared byte_size before writing
 * anything further to disk, so a malformed or malicious client cannot inflate
 * the file past what was declared (and, by construction, past
 * imports.max_upload_bytes).
 */
final class ImportUploadController extends Controller
{
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

        $batch = ImportBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'kind' => $kind,
            'status' => ImportStatus::Uploading,
            // Stored for display only (see class docblock) — never used to
            // build a filesystem path.
            'original_filename' => $validated['filename'],
            'byte_size' => $validated['byte_size'],
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

        // Measured with the same filesize() used everywhere else in this class
        // (never UploadedFile::getSize()) so the number that gates the write
        // below is the exact byte count appendChunk() will stream from — no
        // room for the two to disagree.
        $chunkSize = filesize($chunkPath);

        abort_if($chunkSize === false, 422, __('imports.errors.invalid_chunk'));

        // The read (received_chunks / current file size), the decision, and the
        // write are locked together for the lifetime of this batch row so two
        // concurrent requests for the same {uuid} (a crafted double-submit, not
        // just a dropped connection) cannot both pass the same check and each
        // append their chunk — the second one blocks until the first commits,
        // then sees the updated state.
        return DB::transaction(function () use ($request, $uuid, $index, $chunkPath, $chunkSize): JsonResponse {
            $batch = $this->ownedBatch($request, $uuid, lockForUpdate: true);

            abort_unless($batch->status === ImportStatus::Uploading, 409, __('imports.errors.not_uploading'));

            if ($index !== $batch->received_chunks) {
                return response()->json([
                    'message' => __('imports.errors.out_of_order'),
                    'expected_index' => $batch->received_chunks,
                ], 409);
            }

            $partial = $this->partialPath($batch);
            $absolute = Storage::disk('local')->path($partial);
            $currentSize = is_file($absolute) ? (int) filesize($absolute) : 0;

            // The client's declared byte_size is the hard ceiling for what this
            // batch may ever write to disk (and byte_size itself was already
            // capped at imports.max_upload_bytes when the batch was created).
            // Without this check, a client that keeps sending well-formed,
            // correctly-indexed chunks past the declared size — or a single
            // oversized chunk — would grow the file without bound.
            if ($currentSize + $chunkSize > $batch->byte_size) {
                $batch->markFailed(__('imports.errors.size_exceeded'));

                return response()->json([
                    'message' => __('imports.errors.size_exceeded'),
                ], 422);
            }

            Storage::disk('local')->makeDirectory(dirname($partial));
            $this->appendChunk($absolute, $chunkPath);

            $batch->increment('received_chunks');

            return response()->json(['next_index' => $batch->received_chunks]);
        });
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $batch = $this->ownedBatch($request, $uuid);

        abort_unless($batch->status === ImportStatus::Uploading, 409, __('imports.errors.not_uploading'));

        $partial = $this->partialPath($batch);
        $absolute = Storage::disk('local')->path($partial);
        $actual = is_file($absolute) ? (int) filesize($absolute) : 0;

        if ($actual !== (int) $batch->byte_size) {
            $batch->markFailed(__('imports.errors.size_mismatch', ['expected' => $batch->byte_size, 'actual' => $actual]));

            return response()->json(['message' => $batch->error_message], 422);
        }

        $transitioned = $batch->transitionTo(ImportStatus::Uploaded, [
            'stored_path' => $absolute,
            'checksum' => hash_file('sha256', $absolute),
        ]);

        // A false result means the batch was no longer Uploading by the time the
        // write raced against another request for the same {uuid} (a replayed
        // or double-submitted complete()) — do not dispatch analysis twice.
        abort_unless($transitioned, 409, __('imports.errors.not_uploading'));

        $batch->dispatchAnalysis();

        return response()->json(['uuid' => $batch->uuid, 'status' => $batch->status->value]);
    }

    /**
     * Loads the batch for {uuid} and enforces that it belongs to the
     * authenticated user. {uuid} is an attacker-controlled route parameter, so
     * every endpoint that accepts one must go through here rather than loading
     * the batch directly.
     */
    private function ownedBatch(Request $request, string $uuid, bool $lockForUpdate = false): ImportBatch
    {
        $this->authorize('imports.create');

        $query = ImportBatch::where('uuid', $uuid);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $batch = $query->firstOrFail();

        abort_unless($batch->user_id === $request->user()->id, 403);

        return $batch;
    }

    /**
     * The on-disk path for a batch's in-progress upload, derived only from the
     * server-generated uuid — never from the client-supplied original_filename,
     * which must not be able to influence a filesystem path.
     */
    private function partialPath(ImportBatch $batch): string
    {
        return 'imports/'.$batch->uuid.'/source.part';
    }

    /**
     * Appends the chunk to the partial file via a stream copy rather than
     * reading it into a PHP string with file_get_contents(): the caller has
     * already bounded the chunk against the batch's remaining allowance, but
     * streaming keeps memory use flat regardless of chunk size.
     */
    private function appendChunk(string $destination, string $source): void
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

        try {
            stream_copy_to_stream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }
}
