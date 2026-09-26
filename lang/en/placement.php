<?php

declare(strict_types=1);

return [
    'title' => 'Parcels outside their district',
    'subtitle' => 'Parcels whose polygon lies outside the district (or city) their plan is recorded in, with where they actually are by the National Address boundaries. These are warnings to review: a boundary may be out of date or approximate.',
    'tabs' => [
        'district' => 'Outside the district',
        'city' => 'Outside the city',
    ],
    'hint' => [
        'district' => 'Parcels whose plan\'s district has an official boundary, and whose polygon lies outside it.',
        'city' => 'Parcels whose plan\'s district has no boundary, checked against the city instead, and lying outside it.',
    ],
    'col_parcel' => 'Parcel',
    'col_plan' => 'Plan',
    'col_recorded' => 'Recorded in',
    'col_actual' => 'Actually in',
    'approximate' => 'approximate boundary',
    'open' => 'Open parcel',
    'all_good' => 'No parcels lie outside their boundaries.',
    'fix' => [
        'button' => 'Reassign',
        'title' => 'Reassign the parcel\'s district',
        'why' => 'A parcel is not recorded in a district by itself: it takes its plan\'s district, and the plan takes its district\'s city. So the record is corrected at the plan or the district, and the correction covers every parcel linked to it.',
        'plan' => 'Move plan :subject to «:to»',
        'district' => 'Move district «:subject» to the city «:to»',
        'from_plan' => 'Current district: :from',
        'from_district' => 'Current city: :from',
        'scope_plan' => 'Covers :parcels parcels in this plan.',
        'scope_district' => 'Covers every plan of the district and its :parcels parcels.',
        'inside' => ':inside of the :located with a polygon lie inside «:to».',
        'rest' => ':count parcels will still lie outside their boundary after the move, and stay on this list for review.',
        'recommended' => 'Suggested',
        'apply' => 'Apply',
        'close' => 'Close',
        'none' => 'No automatic correction for this parcel: it lies outside every district and city with a boundary, or the polygon itself is wrong. Open the parcel and check its polygon or its district\'s boundary.',
        'stale' => 'The parcel\'s data changed since the dialog opened. Open it again.',
        'done_plan' => 'Plan :subject moved to «:to» with :parcels parcels.',
        'done_district' => 'District «:subject» moved to the city «:to» with :parcels parcels.',
    ],
];
