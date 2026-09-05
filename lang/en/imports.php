<?php

declare(strict_types=1);

return [
    'title' => 'Data import',
    'kind' => [
        'label' => 'File type',
        'gdb' => 'Geodatabase (zipped GDB)',
        'documents' => 'Zipped PDF documents',
    ],
    'choose_file' => 'Choose a file',
    'upload' => 'Upload',
    'uploading' => 'Uploading…',
    'analyzing' => 'Analysing…',
    'committing' => 'Saving…',
    'preview' => [
        'title' => 'Analysis result',
        'total' => 'Total items',
        'will_create' => 'Will be created',
        // Documents import counts something different under the same shape:
        // "created" there is parcel–file LINKS (one deed can link to several
        // parcels), while "unmatched" counts FILES. The two are not a
        // matched pair, so they get their own, unit-explicit labels instead
        // of reusing will_create/unmatched — see import-wizard.blade.php.
        'will_create_links' => 'Parcel links to be created',
        'will_update' => 'Will be updated',
        'unmatched' => 'Unmatched',
        'unmatched_files' => 'Files not linked to any parcel',
        'rule' => 'Matching rule',
        'warnings' => 'Warnings',
    ],
    'confirm' => 'Confirm import',
    'cancel' => 'Cancel',
    'completed' => 'Import complete',
    'failed' => 'Import failed',
    'start_over' => 'Import another file',
    'recent' => [
        'title' => 'Recent imports',
        'file' => 'File',
        'uploader' => 'By',
        'status' => 'Status',
        'date' => 'Date',
        'empty' => 'No imports yet.',
    ],
    'status' => [
        'uploading' => 'Uploading',
        'uploaded' => 'Uploaded',
        'analyzing' => 'Analysing',
        'previewed' => 'Awaiting confirmation',
        'committing' => 'Saving',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'errors' => [
        'extension' => 'Unsupported file type. Allowed: :allowed',
        'invalid_chunk' => 'The uploaded file chunk is not valid.',
        'not_uploading' => 'This import is no longer accepting chunks.',
        'out_of_order' => 'A file chunk arrived out of order.',
        'size_exceeded' => 'The uploaded file exceeds the size declared for this import.',
        'size_mismatch' => 'The received file size (:actual) does not match the expected size (:expected).',
        'invalid_archive' => 'The file is corrupt or does not match the expected type.',
    ],
    'warnings' => [
        'no_geo_id' => ':count feature(s) with no Geo_ID are skipped.',
    ],
];
