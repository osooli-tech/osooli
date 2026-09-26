/**
 * Chunked upload for the dashboard import wizard. Slices the file client-side
 * and posts chunks sequentially, driving the three endpoints Task 8 added:
 * imports.upload.create, .chunk and .complete.
 *
 * The chunk endpoint answers an out-of-order chunk with 409 and the index it
 * actually wants (expected_index). This resyncs to that index instead of
 * retrying blindly — that is what makes a dropped connection resumable
 * rather than something that has to restart the whole upload from zero. A
 * resync that keeps naming the same index (two tabs racing the same batch, a
 * stuck received_chunks counter) is bounded: after MAX_STUCK_RESYNCS
 * non-advancing resyncs in a row, the upload gives up with a translated
 * error instead of looping forever behind a progress bar that never moves.
 *
 * Every response is decoded through readJson() rather than a bare
 * response.json(): a 419 (CSRF/session expiry mid-upload) or a 500/502 from
 * a gateway in front of the app returns an HTML page, and .json() on that
 * throws a raw SyntaxError. readJson() falls back to a translated generic
 * message instead of surfacing that parser error to the user.
 */

/** Consecutive 409s naming the same expected_index before this gives up. */
const MAX_STUCK_RESYNCS = 5;

/**
 * Parses a fetch Response as JSON, falling back to { message: fallback }
 * when the body is not valid JSON.
 */
async function readJson(response, fallback) {
    try {
        return await response.json();
    } catch {
        return { message: fallback };
    }
}

export async function uploadImport(file, kind, { onProgress, messages = {} } = {}) {
    // These defaults only apply if a caller forgets to pass `messages` — the
    // blade template always supplies the real, locale-aware strings via
    // @js(__('imports.errors....')).
    const {
        stuckResync = 'The upload appears to be stuck. Please try again.',
        invalidResponse = 'The server returned an unexpected response. Please try again.',
    } = messages;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };

    const started = await fetch('/imports/upload', {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({ kind, filename: file.name, byte_size: file.size }),
    });

    if (!started.ok) throw new Error((await readJson(started, invalidResponse)).message);

    const { uuid, chunk_bytes: chunkBytes } = await readJson(started, invalidResponse);

    let index = 0;
    let lastResyncTarget = null;
    let stuckResyncs = 0;

    while (index * chunkBytes < file.size) {
        const slice = file.slice(index * chunkBytes, (index + 1) * chunkBytes);
        const body = new FormData();
        body.append('index', String(index));
        body.append('chunk', slice, 'chunk');

        const response = await fetch(`/imports/upload/${uuid}/chunk`, { method: 'POST', headers, body });

        if (response.status === 409) {
            // The server tells us which chunk it actually wants; resync
            // rather than retrying blindly, which is what makes a dropped
            // connection resumable instead of corrupting the assembled
            // file. But if the named index never advances, resyncing is
            // going nowhere (two tabs racing the same batch, a stuck
            // counter) — cap it instead of spinning forever.
            const { expected_index: expectedIndex } = await readJson(response, invalidResponse);

            stuckResyncs = expectedIndex === lastResyncTarget ? stuckResyncs + 1 : 0;
            lastResyncTarget = expectedIndex;

            if (stuckResyncs >= MAX_STUCK_RESYNCS) throw new Error(stuckResync);

            index = expectedIndex;
            continue;
        }

        if (!response.ok) throw new Error((await readJson(response, invalidResponse)).message);

        stuckResyncs = 0;
        lastResyncTarget = null;
        index = (await readJson(response, invalidResponse)).next_index;
        onProgress?.(Math.min(1, (index * chunkBytes) / file.size));
    }

    const completed = await fetch(`/imports/upload/${uuid}/complete`, { method: 'POST', headers });

    if (!completed.ok) throw new Error((await readJson(completed, invalidResponse)).message);

    return uuid;
}
