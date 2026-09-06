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
    'result' => [
        // Distinct from 'failed' below on purpose: this counts per-feature
        // errors inside an otherwise-completed batch (rendered in the
        // Completed panel), while 'failed' labels the batch's own terminal
        // state (rendered in the Failed panel) — see import-wizard.blade.php.
        // Before this key existed, the Completed panel rendered a per-feature
        // error count under the 'failed' label itself, so "Import complete"
        // sat directly above "Import failed: 6".
        'errors' => 'Features with errors',
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
        // Shown instead of dispatching a commit when the staged archive has
        // already been pruned (PruneImportBatches reaps any status,
        // including a still-Previewed batch, past the retention window and
        // nulls stored_path) — see ImportWizard::confirm().
        'staged_file_missing' => 'The staged file for this import is no longer available. Please start a new import.',
        // Both purely client-side: the JS never receives a server response
        // for these, so unlike the keys above they are read through
        // @js(__(...)) in import-wizard.blade.php and handed to
        // uploadImport() rather than coming back in a JSON body.
        'stuck_resync' => 'The upload appears to be stuck. Please try again.',
        'unexpected_response' => 'The server returned an unexpected response. Please try again.',
    ],
    'warnings' => [
        // Mirrors the Arabic file's explicit count branches (see
        // lang/ar/imports.php) even though English grammar only needs
        // singular vs. plural — kept structurally parallel so both files
        // branch on the same count ranges via trans_choice().
        'no_geo_id' => '{0} All features have a Geo_ID; none are skipped.|{1} :count feature with no Geo_ID is skipped.|{2} :count features with no Geo_ID are skipped.|[3,10] :count features with no Geo_ID are skipped.|[11,99] :count features with no Geo_ID are skipped.|[100,*] :count features with no Geo_ID are skipped.',
    ],
];
