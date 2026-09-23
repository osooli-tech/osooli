<?php

declare(strict_types=1);

/**
 * Vocabulary for the reference-data screen. Mirrors lang/ar/reference.php.
 */
return [
    'title' => 'Reference data',
    'subtitle' => 'Manage plans, districts, cities, regions, countries and engineering offices',

    'tabs' => [
        'plans' => 'Plans',
        'districts' => 'Districts',
        'cities' => 'Cities',
        'regions' => 'Regions',
        'countries' => 'Countries',
        'offices' => 'Engineering offices',
    ],

    'create' => [
        'plans' => 'Add plan',
        'districts' => 'Add district',
        'cities' => 'Add city',
        'regions' => 'Add region',
        'countries' => 'Add country',
        'offices' => 'Add engineering office',
    ],

    'edit' => [
        'plans' => 'Edit plan',
        'districts' => 'Edit district',
        'cities' => 'Edit city',
        'regions' => 'Edit region',
        'countries' => 'Edit country',
        'offices' => 'Edit engineering office',
    ],

    'search' => [
        'plans' => 'Search by plan number...',
        'districts' => 'Search by district name...',
        'cities' => 'Search by city name...',
        'regions' => 'Search by region name...',
        'countries' => 'Search by country name...',
        'offices' => 'Search by office name, licence number or phone...',
    ],

    'empty' => [
        'plans' => 'No plans recorded',
        'districts' => 'No districts recorded',
        'cities' => 'No cities recorded',
        'regions' => 'No regions recorded',
        'countries' => 'No countries recorded',
        'offices' => 'No engineering offices recorded',
    ],

    'dependents' => [
        'plans' => 'Parcels',
        'districts' => 'Plans',
        'cities' => 'Districts',
        'regions' => 'Cities',
        'countries' => 'Regions',
        'offices' => 'Boundary records',
    ],

    'blocked' => [
        'plans' => 'This plan cannot be deleted: :count parcel(s), archived ones included, still reference it. Move them to another plan first.',
        'districts' => 'This district cannot be deleted: :count plan(s) still reference it. Move them to another district first.',
        'cities' => 'This city cannot be deleted: :count district(s) still reference it. Move them to another city first.',
        'regions' => 'This region cannot be deleted: :count city/cities still reference it. Move them to another region first.',
        'countries' => 'This country cannot be deleted: :count region(s) still reference it. Move them to another country first.',
        'offices' => 'This engineering office cannot be deleted: :count boundary record(s) are credited to it. Detach them first.',
    ],

    // Fields
    'plan_no' => 'Plan number',
    'district' => 'District',
    'city' => 'City',
    'region' => 'Region',
    'country' => 'Country',
    'name_ar' => 'Name (Arabic)',
    'name_en' => 'Name (English)',
    'iso_code' => 'ISO code',
    'office_name' => 'Office name',
    'license_no' => 'Licence number',
    'phone' => 'Phone',
    'email' => 'Email',

    // Values
    'unassigned' => 'Not set',
    'district_hint' => 'The district may be left empty and set later.',

    // Delete dialog
    'delete_title' => 'Delete reference record',
    'delete_blocked_title' => 'Cannot delete',
    'close' => 'Close',
];
