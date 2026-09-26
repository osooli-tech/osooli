<?php

declare(strict_types=1);

// "What is this page?" — shown above each page by <x-page-help>, keyed by
// route name. what: one or two sentences; steps: what one does here; note:
// the one thing worth knowing before acting.
return [
    'what_is_this' => 'What is this page?',

    'pages' => [
        'dashboard' => [
            'what' => 'The home page: a map of every parcel with quick figures about them. Click a parcel on the map to see its details.',
            'steps' => [
                'From the layer panel: show or hide parcels, projects, buildings, administrative boundaries and custom layers.',
                'Search for a parcel by number or GEO ID, or filter the map by city or owner.',
            ],
        ],
        'parcels_index' => [
            'what' => 'Every parcel and deed on record, with search and filters.',
            'steps' => [
                'Open a parcel to see its details and documents and edit it.',
                'Export the list to Excel or PDF with the filters chosen.',
            ],
        ],
        'parcels_show' => [
            'what' => 'Everything about one parcel: its location and polygon, deeds and owners, boundaries and lengths, survey decision and documents.',
            'steps' => [
                'Edit its details or draw its polygon from the buttons on the page.',
                'Print the parcel report, or open the "digital twin" for its single file.',
            ],
            'note' => 'A warning shows if the parcel\'s polygon lies outside its recorded district.',
        ],
        'parcels_twin' => [
            'what' => 'The digital twin: one file bringing together everything recorded about the parcel, to read and present.',
        ],
        'parcels_documents' => [
            'what' => 'One parcel\'s documents: scanned deeds, survey sheets and photos.',
        ],
        'parcels_placement' => [
            'what' => 'Parcels whose polygon lies outside the district (or city) their plan is recorded in, by the National Address boundaries, with where they actually are.',
            'note' => 'These are warnings to review, not certain errors: a boundary may be out of date or approximate. Fix the plan, the polygon or the boundary as the case needs.',
        ],
        'owners_index' => [
            'what' => 'Owners with their parcels and deeds, and owner portfolios (an owner\'s parcels in groups).',
            'steps' => [
                'Show an owner\'s or a portfolio\'s parcels on the map.',
                'Print the full owner report.',
            ],
        ],
        'documents_index' => [
            'what' => 'Every document and photo uploaded for the parcels, and their review.',
            'steps' => [
                'Upload a document for a parcel, or search documents by type and status.',
                'A reviewer approves a document, or rejects it with a reason.',
            ],
            'note' => 'An uploaded document reaches owners only once approved.',
        ],
        'documents_split' => [
            'what' => 'Upload one PDF holding pages for many parcels (an ArcGIS map series, survey sheets scanned together). The file is split and each page filed against its parcel.',
            'steps' => [
                'Drop the file on the page. Pages are read from their text, or from their image by OCR.',
                'Check the matches and correct any, and group the pages of a document longer than one page.',
                'Press "Split the file and upload the parts".',
            ],
            'note' => 'The parcels must be on record before their documents are uploaded: import the GDB first.',
        ],
        'archive_index' => [
            'what' => 'Archived records (parcels, deeds, owners) and the values a data cleanup corrected. Archived is out of lists and reports, not deleted.',
            'note' => '"Restore" brings a record or value back as it was. Take care on the corrected-values tab: it brings back the error that was fixed.',
        ],
        'imports_index' => [
            'what' => 'Import a GeoJSON file in the export\'s format: an exported file you edited, or a new one in the same format.',
            'steps' => [
                'Download the "sample file" to see the columns.',
                'Upload the file: it is analysed without writing anything, and the changes and questions are shown.',
                'Answer the questions and confirm. An import can be undone within 7 days.',
            ],
        ],
        'imports_gdb' => [
            'what' => 'Import a zipped Geodatabase (GDB), or a document archive. The whole file is analysed and every layer and field shown before anything is written.',
            'steps' => [
                'Upload the file and choose its kind.',
                'On the review: set where each layer goes, match districts by name or location, and choose plans, boundaries and the engineering office.',
                'Press "Confirm import".',
            ],
            'note' => 'An empty field in the file never erases the value on record. Any earlier import can be reopened for review from the recent-imports table.',
        ],
        'exports_index' => [
            'what' => 'Export deeds with their parcels and owners to a GeoJSON file that opens in GIS software and can be edited and imported back.',
            'steps' => [
                'Choose the filters and the parts to include (polygons, owners, boundaries, documents…).',
                'Press "Export", then download the file from the list of exports.',
            ],
        ],
        'map-layers_index' => [
            'what' => 'Custom layers: layers brought in from GDB files other than parcels, projects and buildings (wells, roads, fences…), with all their fields.',
            'steps' => [
                'Rename a layer or change its colour, and choose whether it shows when the map opens.',
                'Delete a layer no longer needed.',
            ],
            'note' => 'They appear in the layer panel of the main map; clicking a feature shows its fields.',
        ],
        'reference_index' => [
            'what' => 'Reference data: plans, districts, cities, regions, countries and engineering offices, which the parcels rest on.',
            'steps' => [
                'Add, edit, or delete what no parcel rests on.',
                'The "Boundary" button on a district, city or region opens its boundary editor on the map.',
            ],
            'note' => 'A record other records rest on cannot be deleted; the last column shows how many.',
        ],
        'modification-requests_index' => [
            'what' => 'Requests from owners, through their portal, to change parcel details. Review each and approve or reject it.',
        ],
        'presentation-requests_index' => [
            'what' => 'Presentation requests sent by visitors to the public site, to contact and follow up.',
        ],
        'survey-decisions_index' => [
            'what' => 'Survey decisions recorded for the parcels: decision number, report number, source and folder.',
        ],
        'users_index' => [
            'what' => 'Dashboard users: add them, set their roles, activate or deactivate them.',
            'note' => 'A role decides what a user sees and may do. Roles and their permissions are managed under "Settings".',
        ],
        'audit-logs_index' => [
            'what' => 'A log of every sensitive operation: who did what and when (add, edit, archive, import, document download…). For follow-up and review only.',
        ],
        'settings_index' => [
            'what' => 'System settings: roles and each role\'s permissions, the legal pages, and database settings.',
        ],
        'settings_database' => [
            'what' => 'The primary and backup databases and the sync between them.',
            'note' => 'Changing the primary database changes where all data is kept. Make sure they are in sync before switching.',
        ],
        'profile_index' => [
            'what' => 'Your own account: name, email, password and language.',
        ],
    ],
];
