<?php

declare(strict_types=1);

return [
    'title' => 'Custom map layers',
    'subtitle' => 'Map layers imported from geodatabases other than parcels, projects and buildings, with all their fields. They appear in the layer panel of the main map.',
    'col' => [
        'name' => 'Layer',
        'geometry' => 'Geometry',
        'features' => 'Features',
        'fields' => 'Fields',
        'source' => 'Source',
        'on_map' => 'When the map opens',
    ],
    'color' => 'Colour',
    'visible' => 'Shown when the map opens',
    'shown' => 'shown',
    'hidden' => 'hidden',
    'similar' => 'The name is close to: :names',
    'delete_confirm' => 'Delete the layer «:name» and its :count features? This cannot be undone.',
    'empty' => 'No custom layers yet. They are made by the geodatabase import, by setting any layer of the file to "custom layer".',
];
