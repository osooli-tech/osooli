<?php

declare(strict_types=1);

return [
    'title' => 'Custom map layers',
    'subtitle' => 'Every map layer imported from a geodatabase except the parcels (projects and buildings included), with all their fields. They appear in the layer panel of the main map, and can be hidden, downloaded as GeoJSON or deleted here.',
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
    'toggle' => 'Show or hide when the map opens',
    'download' => 'Download as GeoJSON',
    'similar' => 'The name is close to: :names',
    'delete_confirm' => 'Delete the layer «:name» and its :count features? This cannot be undone.',
    'empty' => 'No custom layers yet. They are made by the geodatabase import, by setting any layer of the file to "custom layer".',
];
