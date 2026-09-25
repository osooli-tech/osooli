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
];
