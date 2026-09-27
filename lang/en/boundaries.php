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
        'parcels' => 'Drawn from its parcels',
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
    'missing' => [
        'button' => 'Missing boundaries',
        'title' => 'Districts, cities and regions with no boundary',
        'intro' => 'The National Address boundaries cover the whole kingdom, so anything without one was added by an import or by name only. A district is drawn from where its parcels lie, inside its city and never overlapping a district that has a boundary. A city or region already lies within a National Address one, so it is not drawn on top of it: what is under it moves there instead. Nothing is drawn or moved unless at least half of its parcels agree.',
        'none' => 'Every district, city and region has a boundary.',
        'levels' => [
            'districts' => 'Districts',
            'cities' => 'Cities',
            'regions' => 'Regions',
        ],
        'col_name' => 'Name',
        'col_parcels' => 'Parcels with a polygon',
        'col_finding' => 'Finding',
        'draw' => 'Draw',
        'draw_all' => 'Draw all (:count)',
        'drawn' => ':count district boundaries drawn from their parcels.',
        'findings' => [
            'districts' => [
                'draw' => 'Its parcels lie inside its city «:parent»; its boundary is drawn from them.',
                'merge' => 'Its parcels lie inside «:target», which has a boundary: the same district under another name.',
                'move' => 'Its parcels lie in «:target», not «:parent». Move it there first, then draw it.',
                'scattered' => 'Its parcels are spread over several places; review it by hand.',
                'no_parcels' => 'No parcels with a polygon to draw it from.',
            ],
            'cities' => [
                'merge' => 'Its land is part of the National Address city «:target».',
                'scattered' => 'Its parcels are spread over several cities; review it by hand.',
                'no_parcels' => 'No parcels with a polygon. Delete it if unused.',
            ],
            'regions' => [
                'merge' => 'Its land is part of «:target» in the National Address.',
                'scattered' => 'Its parcels are spread over several regions; review it by hand.',
                'no_parcels' => 'No parcels with a polygon. Delete it if unused.',
            ],
        ],
        'actions' => [
            'districts' => [
                'merge' => 'Move its plans to «:target»',
                'move' => 'Move it to «:target»',
            ],
            'cities' => ['merge' => 'Move its districts to «:target»'],
            'regions' => ['merge' => 'Move its cities to «:target»'],
        ],
        'moved' => [
            'districts' => '«:name» moved to «:target».',
            'cities' => 'The districts of «:name» moved to «:target».',
            'regions' => 'The cities of «:name» moved to «:target».',
        ],
    ],
];
