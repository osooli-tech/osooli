<?php

declare(strict_types=1);

return [
    'button' => 'Boundary',
    'title' => [
        'districts' => 'District boundary',
        'cities' => 'City boundary',
        'regions' => 'Region boundary',
        'countries' => 'Country boundary',
    ],
    'source_label' => 'Current boundary source',
    'sources' => [
        'official' => 'National Address (official)',
        'derived' => 'Derived from National Address districts',
        'approximate' => 'Approximate',
        'manual' => 'Drawn by hand',
        'none' => 'No boundary',
    ],
    'manual_note' => 'A boundary saved here is marked as manual, and reloading the National Address boundaries later never replaces it. It is used to check that parcels lie inside their district and city.',
    'area' => 'Drawn area',
    'km2' => 'km²',
    'vertices' => 'Vertices',
    'neighbours_hint' => 'The grey outlines are the neighbouring boundaries at the same level, to trace a shared edge against.',
    'save' => 'Save boundary',
    'saved' => 'Boundary saved.',
    'remove' => 'Remove boundary',
    'remove_confirm' => 'Remove this boundary? Parcels will not be checked at this level until it is drawn again or the National Address boundaries are reloaded.',
    'removed' => 'Boundary removed.',
];
