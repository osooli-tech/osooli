<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Landing page
    |--------------------------------------------------------------------------
    |
    | Values the public marketing page renders. They live here rather than in
    | the views so marketing copy can change without touching Blade, and here
    | rather than in the database so an anonymous visit costs no query.
    |
    */

    'contact_email' => env('LANDING_CONTACT_EMAIL', 'sales@sakuki.sa'),

    'contact_phone' => env('LANDING_CONTACT_PHONE', '+966597535359'),
    'contact_whatsapp' => env('LANDING_CONTACT_WHATSAPP', '+966597535359'),

    'app_store_url' => env('LANDING_APP_STORE_URL', '#'),
    'play_store_url' => env('LANDING_PLAY_STORE_URL', '#'),

    /*
    | Headline figures in the hero. Written as display strings because they are
    | localised numerals, not values to compute with. Keep them true: they are
    | the first claim a visitor reads.
    */
    'stats' => [
        'parcels' => env('LANDING_STAT_PARCELS', '٤٨٬٢٠٦'),
        'area' => env('LANDING_STAT_AREA', '١٢٧ م²'),
        'updated' => env('LANDING_STAT_UPDATED', '٩٦٪'),
    ],

];
