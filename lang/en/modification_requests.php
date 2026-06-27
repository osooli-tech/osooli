<?php

declare(strict_types=1);

return [
    'title' => 'Modification Requests',
    'subtitle' => 'Parcel data change requests submitted by owners',
    'total' => 'request|requests',
    'empty' => 'No modification requests',
    'empty_filtered' => 'No requests with this status',
    'search_placeholder' => 'Search by parcel number or field name...',

    // Table columns
    'col_parcel' => 'Parcel No.',
    'col_owner' => 'Owner',
    'col_field' => 'Field',
    'col_old' => 'Current Value',
    'col_new' => 'Requested Value',
    'col_status' => 'Status',
    'col_date' => 'Submitted',
    'col_actions' => 'Actions',

    // Status labels
    'status' => [
        'all' => 'All',
        'pending' => 'Pending',
        'sent_to_arcgis' => 'Sent to ArcGIS',
        'applied' => 'Applied',
        'rejected' => 'Rejected',
    ],

    // Detail modal
    'modal_title' => 'Request Details',
    'request_info' => 'Request Info',
    'current_value' => 'Current Value',
    'requested_value' => 'Requested Value',
    'submitted_at' => 'Submitted At',
    'resolved_at' => 'Resolved At',
    'notes_label' => 'Existing Notes',
    'manager_note' => 'Note (optional)',
    'manager_note_placeholder' => 'Reason for change or note for the team...',
    'close' => 'Close',

    // Actions
    'action_send' => 'Send to ArcGIS',
    'action_apply' => 'Confirm Applied',
    'action_reject' => 'Reject',

    // Toasts
    'status_updated' => 'Request status updated',
    'invalid_transition' => 'This status transition is not allowed',

    // Audit
    'audit_changed' => 'modification_request status changed',
];
