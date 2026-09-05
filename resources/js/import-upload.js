/**
 * Chunked upload for the dashboard import wizard. Slices the file client-side
 * and posts chunks sequentially, driving the three endpoints Task 8 added:
 * imports.upload.create, .chunk and .complete.
 *
 * The chunk endpoint answers an out-of-order chunk with 409 and the index it
 * actually wants (expected_index). This resyncs to that index instead of
 * retrying blindly — that is what makes a dropped connection resumable
 * rather than something that has to restart the whole upload from zero.
 */
export async function uploadImport(file, kind, { onProgress } = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const headers = { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' };

    const started = await fetch('/imports/upload', {
        method: 'POST',
        headers: { ...headers, 'Content-Type': 'application/json' },
        body: JSON.stringify({ kind, filename: file.name, byte_size: file.size }),
    });

    if (!started.ok) throw new Error((await started.json()).message);

    const { uuid, chunk_bytes: chunkBytes } = await started.json();

    let index = 0;
    while (index * chunkBytes < file.size) {
        const slice = file.slice(index * chunkBytes, (index + 1) * chunkBytes);
        const body = new FormData();
        body.append('index', String(index));
        body.append('chunk', slice, 'chunk');

        const response = await fetch(`/imports/upload/${uuid}/chunk`, { method: 'POST', headers, body });

        if (response.status === 409) {
            // The server tells us which chunk it actually wants; resync rather
            // than retrying blindly, which is what makes a dropped connection
            // resumable instead of corrupting the assembled file.
            index = (await response.json()).expected_index;
            continue;
        }

        if (!response.ok) throw new Error('Chunk upload failed');

        index = (await response.json()).next_index;
        onProgress?.(Math.min(1, (index * chunkBytes) / file.size));
    }

    const completed = await fetch(`/imports/upload/${uuid}/complete`, { method: 'POST', headers });

    if (!completed.ok) throw new Error((await completed.json()).message);

    return uuid;
}
