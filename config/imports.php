<?php

declare(strict_types=1);

return [
    // Absolute path to the GDAL ogr2ogr binary. A File Geodatabase cannot be
    // read from PHP, so the GDB import shells out to this.
    'ogr2ogr_path' => env('IMPORT_OGR2OGR_PATH', 'ogr2ogr'),

    // Absolute path to the GDAL ogrinfo binary, used only to enumerate a
    // geodatabase's layers before conversion. Ships in the same GDAL
    // package as ogr2ogr.
    'ogrinfo_path' => env('IMPORT_OGRINFO_PATH', 'ogrinfo'),

    // Upload chunk size, in bytes. Kept under post_max_size so the browser can
    // send large archives without a server configuration change.
    'chunk_bytes' => (int) env('IMPORT_CHUNK_BYTES', 2 * 1024 * 1024),

    'max_upload_bytes' => (int) env('IMPORT_MAX_UPLOAD_BYTES', 512 * 1024 * 1024),

    'max_archive_entries' => (int) env('IMPORT_MAX_ARCHIVE_ENTRIES', 5000),

    'max_archive_bytes' => (int) env('IMPORT_MAX_ARCHIVE_BYTES', 2 * 1024 * 1024 * 1024),

    // Days to keep a staged upload before PruneImportBatches deletes it. The
    // batch row is kept as history; only the archive is removed.
    'retention_days' => (int) env('IMPORT_RETENTION_DAYS', 7),

    // Run analyze/commit inline instead of on the queue. For hosts with no
    // queue worker, where a queued job would leave the page waiting forever.
    //
    // Note: the jobs' $timeout property is NOT enforced in this mode —
    // Laravel's SyncQueue has no timeout machinery at all (that is Laravel's
    // design, not a bug here), so the real ceiling becomes PHP's
    // max_execution_time and the web server's gateway timeout. Intended for
    // hosts without a queue worker and for modest-sized imports; a host that
    // expects large imports should run a real queue worker instead.
    'queue_sync' => (bool) env('IMPORT_QUEUE_SYNC', false),
];
