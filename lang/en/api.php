<?php

declare(strict_types=1);

return [
    // Auth
    'otp_sent' => 'Verification code sent',
    'otp_invalid' => 'Invalid verification code',
    'phone_not_registered' => 'Phone number is not registered',
    'too_many_attempts' => 'Too many attempts, try again shortly',
    'logged_out' => 'Signed out',
    'unauthenticated' => 'Unauthenticated',
    'not_found' => 'Not found',
    'server_error' => 'An unexpected error occurred, please try again later',

    // Modification requests
    'modification_request_created' => 'Modification request submitted',
    'field_not_editable' => 'This field cannot be edited',
    'parcel_not_owned' => 'Parcel not found',

    // Profile
    'profile_updated' => 'Profile updated',

    'modification_statuses' => [
        'pending' => 'Under review',
        'sent_to_arcgis' => 'Sent for update',
        'applied' => 'Applied',
        'rejected' => 'Rejected',
    ],
];
