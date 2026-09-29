<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MapLayer;
use App\Support\Database\Dialect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A custom layer as a GeoJSON file, every attribute as it was imported —
 * opens in ArcGIS or QGIS, and comes back in through the Geodatabase import
 * or as a layer of its own. Written feature by feature, so a large layer is
 * never held in memory whole.
 */
class MapLayerDownloadController extends Controller
{
    public function __invoke(Request $request, MapLayer $layer): StreamedResponse
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'download',
            'target_type' => 'map_layer',
            'target_id' => $layer->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $geometry = Dialect::isSpatial() ? 'ST_AsGeoJSON(geom, 7)' : 'NULL';

        return response()->streamDownload(function () use ($layer, $geometry): void {
            echo '{"type":"FeatureCollection","name":'.json_encode($layer->name, JSON_UNESCAPED_UNICODE).',"features":[';

            $first = true;
            $rows = DB::table('map_layer_features')
                ->where('map_layer_id', $layer->id)
                ->orderBy('id')
                ->selectRaw("properties, {$geometry} AS geom_json")
                ->cursor();

            foreach ($rows as $row) {
                $properties = is_string($row->properties) ? json_decode($row->properties, true) : null;
                echo ($first ? '' : ',').json_encode([
                    'type' => 'Feature',
                    'geometry' => $row->geom_json === null ? null : json_decode((string) $row->geom_json, false),
                    'properties' => is_array($properties) && $properties !== [] ? $properties : new \stdClass,
                ], JSON_UNESCAPED_UNICODE);
                $first = false;
            }

            echo ']}';
        }, $layer->name.'.geojson', ['Content-Type' => 'application/geo+json']);
    }
}
