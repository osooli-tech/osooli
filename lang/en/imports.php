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
        'review' => 'Review',
        'open' => 'View',
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
        'unknown_values' => 'Unknown values in «:field» were not written: :values',
    ],
    'gdb' => [
        'layers' => 'Layers in the file',
        'layer' => 'Layer',
        'geometry' => 'Geometry',
        'no_geometry' => 'Table, no geometry',
        'features' => 'Features',
        'crs' => 'Coordinate system',
        'fields' => 'Fields',
        'import_as' => 'Import into',
        'roles' => [
            'parcels' => 'Parcels and deeds',
            'projects' => 'Projects',
            'buildings' => 'Buildings',
            'ignore' => 'Do not import',
        ],
        'mode_label' => 'There are :count :table on record now. On import:',
        'modes' => [
            'replace' => 'replace them with the file\'s',
            'append' => 'add the file\'s to them',
        ],
        'one_parcels_layer' => 'More than one layer is set to parcels; only the first is imported.',
        'field_profile' => 'Fields of :layer',
        'field' => 'Field',
        'type' => 'Type',
        'filled' => 'Filled',
        'distinct' => 'Distinct',
        'samples' => 'Examples',
        'empty' => 'empty',
        'attachments' => 'Attachments, photos and the rest of the file',
        'no_attachments' => 'The file holds no attachment tables (photos or documents stored inside it).',
        'attachment_tables' => 'Attachment tables',
        'attachments_not_imported' => 'Attachments stored inside the file are not imported yet; documents are uploaded through the document import.',
        'photo_fields' => 'Photo fields (filled)',
        'no_relationships' => 'No relationships between tables.',
        'relationships' => 'Relationships',
        'system_tables' => ':count ArcGIS system tables (GDB_*), holding no data and not imported.',
        'coded_values' => 'Values of fixed-choice fields',
        'coded_values_hint' => 'Each value in the file and what it means in the system. An unknown value is not written; the value on record stays.',
        'unknown_value' => 'unknown — not written',
        'districts' => 'Districts',
        'districts_hint' => 'For each district in the file, pick the matching district on record, or leave it empty to create a district of that name in the city chosen for it, or the default city.',
        'default_city' => 'Default city for new districts',
        'district_in_file' => 'District in the file',
        'match_district' => 'Matching district on record',
        'or_create_in' => 'Or create it in city',
        'new_district' => 'New district',
        'several_matches' => 'A district of this name exists in several cities: :cities',
        'no_city' => 'No city: its parcels stay without a district.',
        'plans' => 'Plans',
        'plan_placeholders' => 'Values that mean "no plan" (comma-separated)',
        'plan_placeholders_hint' => 'A parcel whose plan is one of these, or empty, is imported without a plan, and no plan of that name is made. Plans are matched on number and district together.',
        'deeds' => 'Deeds',
        'deeds_counts' => ':with features with a deed number, :without without.',
        'deedless' => [
            'placeholder' => 'Create a numberless deed for each to hold its owners (ownership stays visible; the number is added later)',
            'skip' => 'Import the parcel alone, with no deed and no owners',
        ],
        'borders' => 'Boundaries and lengths',
        'borders_counts' => 'The first set (N_Border…) is filled in :first, the second (N_Border_2…) in :second, of :total features.',
        'border_sets' => [
            'first' => 'Use the first set',
            'second' => 'Use the second set (_2)',
            'prefer_second' => 'The second where present, the first otherwise',
        ],
        'office' => 'Engineering office for new boundaries:',
        'no_office' => 'No office',
        'decision' => 'Survey decision',
        'qrar' => [
            'number' => 'Decision number',
            'source' => 'Decision source (code: 1 municipal, 2 engineering office, 3 none)',
            'ignore' => 'Ignore it',
        ],
        'folder' => [
            'folder' => 'Folder number',
            'ignore' => 'Ignore it (a note, not a folder)',
        ],
        'owners' => 'Owners and portfolios',
        'owners_count' => ':count distinct owners in the file, matched by national ID.',
        'odd_ids' => 'National IDs that are not 10 digits:',
        'portfolios' => 'Create owner portfolios from Real_Estate_portfolio (:count portfolios) and put each parcel in its portfolio',
        'empty_never_erases' => 'An empty field in the file never erases the value on record.',
        'result' => [
            'deeds' => 'New deeds',
            'owners' => 'New owners',
            'boundaries' => 'Boundaries',
            'decisions' => 'New decisions',
            'portfolios' => 'New portfolios',
            'projects' => 'Projects',
            'buildings' => 'Buildings',
        ],
    ],
];
