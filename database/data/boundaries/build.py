"""
Builds the boundary files NationalAddressBoundariesSeeder loads.

    pip install shapely==2.*
    python database/data/boundaries/build.py path/to/source

`source` holds, from github.com/yasseralsamman/saudi-national-address
(data/dist, published CC0, from the SPL National Address):

    districts.geojson, regions.geojson, cities.lite.json

What comes out, gzipped GeoJSON in this directory:

    regions.geojson.gz    13 regions — official (SPL)
    districts.geojson.gz  3,732 districts — official (SPL)
    cities.geojson.gz     4,581 cities and villages:
                            "derived"     — the union of the city's official
                                            districts and its share of the
                                            land around them;
                            "approximate" — no official boundary exists: the
                                            land nearer to this settlement
                                            than to any other in its region
                                            (a Voronoi cell), clipped to the
                                            region.
    country.geojson.gz    Saudi Arabia — the union of the 13 regions

Every polygon is repaired (make_valid) and lightly simplified. Cities and
villages together tile each region with no gaps and no overlaps, so every
point in the kingdom falls in exactly one region and one city — except
about a hundred villages whose centre lies inside a bigger city's official
districts: those get that district as their boundary, inside the city's.
One source district (حي السيح, 10100270048) is a zero-area line and has
no boundary.
"""

import gzip
import json
import sys
from collections import defaultdict
from pathlib import Path

from shapely import make_valid, voronoi_polygons
from shapely.geometry import MultiPoint, MultiPolygon, Point, Polygon, mapping, shape
from shapely.ops import unary_union

OUT = Path(__file__).resolve().parent

# Degrees. ~2 m for districts, ~10 m for the coarser layers.
SIMPLIFY_FINE = 0.00002
SIMPLIFY_COARSE = 0.0001
# Closes the hairline gaps between neighbouring districts when they are merged.
SEAM = 0.0003


def polygons_only(geometry):
    """A (Multi)Polygon, with any stray lines or points make_valid left dropped."""
    geometry = make_valid(geometry)
    if geometry.geom_type == 'Polygon':
        return MultiPolygon([geometry])
    if geometry.geom_type == 'MultiPolygon':
        return geometry
    parts = [g for g in getattr(geometry, 'geoms', []) if g.geom_type in ('Polygon', 'MultiPolygon')]
    flat = []
    for part in parts:
        flat.extend(part.geoms if part.geom_type == 'MultiPolygon' else [part])
    return MultiPolygon(flat) if flat else None


def clean(geometry, tolerance):
    geometry = polygons_only(geometry)
    if geometry is None:
        return None
    return polygons_only(geometry.simplify(tolerance, preserve_topology=True))


def write(name, features):
    with gzip.open(OUT / f'{name}.geojson.gz', 'wt', encoding='utf-8') as out:
        json.dump({'type': 'FeatureCollection', 'features': features}, out, ensure_ascii=False, separators=(',', ':'))
    print(f'{name}: {len(features)} features')


def feature(key, value, geometry, source):
    return {
        'type': 'Feature',
        'properties': {key: value, 'source': source},
        'geometry': mapping(geometry),
    }


def main(source):
    source = Path(source)
    regions = json.loads((source / 'regions.geojson').read_text(encoding='utf-8'))['features']
    districts = json.loads((source / 'districts.geojson').read_text(encoding='utf-8'))['features']
    cities = json.loads((source / 'cities.lite.json').read_text(encoding='utf-8'))

    # Regions and districts: official, repaired.
    region_shapes = {}
    region_features = []
    for f in regions:
        geometry = clean(shape(f['geometry']), SIMPLIFY_COARSE)
        region_shapes[f['properties']['region_id']] = geometry
        region_features.append(feature('region_id', f['properties']['region_id'], geometry, 'official'))
    write('regions', region_features)

    district_features = []
    district_shapes = []
    districts_by_city = defaultdict(list)
    for f in districts:
        geometry = clean(shape(f['geometry']), SIMPLIFY_FINE)
        # One source district is a zero-area line; it has no boundary to keep.
        if geometry is None:
            continue
        district_shapes.append(geometry)
        districts_by_city[f['properties']['city_id']].append(geometry)
        district_features.append(feature('district_id', f['properties']['district_id'], geometry, 'official'))
    write('districts', district_features)

    # Cities: every settlement gets the part of its region nearer to it than
    # to any other; a city with official districts also gets their union.
    city_features = []
    by_region = defaultdict(list)
    for c in cities:
        lat, lng = c['center']
        by_region[c['region_id']].append((c['city_id'], Point(lng, lat)))

    for region_id, members in by_region.items():
        region = region_shapes.get(region_id)
        if region is None:
            continue

        # Districts of this region's cities are theirs outright.
        urban = {cid: unary_union(districts_by_city[cid]).buffer(SEAM).buffer(-SEAM)
                 for cid, _ in members if districts_by_city.get(cid)}
        urban_all = unary_union(list(urban.values())) if urban else Polygon()

        # Several villages share one centre point in the source; nudge them
        # apart so each still gets a cell.
        seen = defaultdict(int)
        points = []
        for cid, point in members:
            key = (round(point.x, 6), round(point.y, 6))
            offset = seen[key] * 0.00001
            seen[key] += 1
            points.append((cid, Point(point.x + offset, point.y + offset)))

        cells = voronoi_polygons(MultiPoint([p for _, p in points]), extend_to=region.envelope.buffer(1))
        cell_list = list(cells.geoms)
        for cid, point in points:
            cell = next((c for c in cell_list if c.contains(point)), None)
            if cell is None:
                cell = min(cell_list, key=lambda c: c.distance(point))

            area = cell.intersection(region).difference(urban_all)
            if cid in urban:
                area = unary_union([area, urban[cid].intersection(region)])
                source_name = 'derived'
            else:
                source_name = 'approximate'

            geometry = clean(area, SIMPLIFY_COARSE)

            # A village swallowed by a bigger city has no land of its own
            # left in the tiling; it gets the official district it sits in.
            if geometry is None or geometry.is_empty:
                holders = [d for d in district_shapes if d.contains(point)]
                if not holders:
                    continue
                geometry = clean(unary_union(holders), SIMPLIFY_COARSE)
                source_name = 'derived'

            city_features.append(feature('city_id', cid, geometry, source_name))
    write('cities', city_features)

    country = clean(unary_union(list(region_shapes.values())), SIMPLIFY_COARSE)
    write('country', [feature('iso_code', 'SA', country, 'official')])


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else '.')
