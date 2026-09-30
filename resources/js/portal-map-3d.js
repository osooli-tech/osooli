// Owner portal only: raises each parcel into a block, then flies the camera
// in at an angle. Two readings of the same parcels: by type (land a low slab
// coloured by deed class, buildings at a typical height — the default) and
// by price (height and colour follow the value). Hooks onto map.js through
// its 'sakuki:layers-added' event, so the shared map code stays untouched.

const container = document.getElementById('sakuki-map');

if (container) {
    const toggle = document.getElementById('map-3d-toggle');
    const modeGroup = document.getElementById('map-3d-mode');
    const massing = JSON.parse(container.dataset.massing || '{}');
    const LAYERS = { type: 'parcels-3d-type', price: 'parcels-3d-price' };
    const MAX_HEIGHT_M = 90;
    const MIN_HEIGHT_M = 8;
    let enabled = true;
    let mode = 'type';
    let flown = false;

    // Value per parcel: the stored price, else price per m² × deed area.
    const valueOf = [
        'coalesce',
        ['get', 'parcel_price'],
        ['*', ['coalesce', ['get', 'm_price'], 0], ['coalesce', ['to-number', ['get', 'deed_area']], 0]],
    ];

    // ['match', ['get', 'massing'], key1, v1, key2, v2, …, fallback]
    function byCategory(field, fallback) {
        const pairs = Object.entries(massing).flatMap(([key, cat]) => [key, cat[field]]);
        return pairs.length ? ['match', ['get', 'massing'], ...pairs, fallback] : fallback;
    }

    const visibility = (layerMode) => (enabled && mode === layerMode ? 'visible' : 'none');

    function addExtrusions(map, maxValue) {
        const beforeLayer = map.getLayer('parcels-labels') ? 'parcels-labels' : undefined;

        if (! map.getLayer(LAYERS.type)) {
            map.addLayer({
                id: LAYERS.type,
                type: 'fill-extrusion',
                source: 'parcels',
                layout: { visibility: visibility('type') },
                paint: {
                    'fill-extrusion-color': byCategory('color', '#c2ae86'),
                    'fill-extrusion-height': byCategory('height', 1.5),
                    'fill-extrusion-base': 0,
                    'fill-extrusion-opacity': 0.9,
                    'fill-extrusion-vertical-gradient': true,
                },
            }, beforeLayer);
        }

        if (! map.getLayer(LAYERS.price)) {
            map.addLayer({
                id: LAYERS.price,
                type: 'fill-extrusion',
                source: 'parcels',
                layout: { visibility: visibility('price') },
                paint: {
                    'fill-extrusion-color': [
                        'interpolate', ['linear'], valueOf,
                        0, '#abc9f2',
                        maxValue * 0.5, '#68dbae',
                        maxValue, '#e6c364',
                    ],
                    'fill-extrusion-height': [
                        'interpolate', ['linear'], valueOf,
                        0, MIN_HEIGHT_M,
                        maxValue, MAX_HEIGHT_M,
                    ],
                    'fill-extrusion-base': 0,
                    'fill-extrusion-opacity': 0.88,
                    'fill-extrusion-vertical-gradient': true,
                },
            }, beforeLayer);
        }
    }

    function applyVisibility(map) {
        Object.entries(LAYERS).forEach(([layerMode, id]) => {
            if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', visibility(layerMode));
        });
    }

    // The type legend lists only the categories this owner's parcels fall in.
    function showPresentCategories(features) {
        const present = new Set(features.map((f) => f.properties.massing));
        document.querySelectorAll('[data-legend="type"] [data-cat]').forEach((li) => {
            li.hidden = ! present.has(li.dataset.cat);
        });
    }

    function flyIn(map, features) {
        if (flown || ! enabled || ! features.length) return;
        flown = true;

        const lngs = features.map((f) => f.properties.centroid_lng).filter((v) => typeof v === 'number');
        const lats = features.map((f) => f.properties.centroid_lat).filter((v) => typeof v === 'number');
        if (! lngs.length) return;

        const center = [(Math.min(...lngs) + Math.max(...lngs)) / 2, (Math.min(...lats) + Math.max(...lats)) / 2];
        const span = Math.max(Math.max(...lngs) - Math.min(...lngs), Math.max(...lats) - Math.min(...lats));
        const zoom = span > 0 ? Math.min(15.4, Math.max(8, Math.log2(360 / span) - 1.6)) : 15.4;

        map.flyTo({ center, zoom, pitch: 55, bearing: -25, duration: 3600, essential: true });
    }

    container.addEventListener('sakuki:layers-added', ({ detail: { map } }) => {
        container.__sakukiMap = map;
        const url = container.dataset.geojsonUrl;
        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => r.json())
            .then((data) => {
                const features = data.features ?? [];
                const values = features.map((f) => {
                    const p = f.properties;
                    return p.parcel_price ?? ((p.m_price ?? 0) * (Number(p.deed_area) || 0));
                });
                const maxValue = Math.max(1, ...values);
                showPresentCategories(features);
                // Fly only once idle: map.js frames the parcels as their data
                // lands, and that fitBounds would cut a flight short mid-tilt.
                const add = () => { addExtrusions(map, maxValue); map.once('idle', () => flyIn(map, features)); };
                map.isStyleLoaded() ? add() : map.once('idle', add);
            })
            .catch(() => console.warn('[Sakuki] 3D layer could not load its data; the flat map still works.'));
    });

    toggle?.addEventListener('click', () => {
        const map = container.__sakukiMap;
        enabled = ! enabled;
        toggle.setAttribute('aria-pressed', String(enabled));
        toggle.querySelector('[data-label]').textContent = enabled ? toggle.dataset.on : toggle.dataset.off;
        if (! map) return;
        applyVisibility(map);
        map.easeTo({ pitch: enabled ? 55 : 0, bearing: enabled ? -25 : 0, duration: 1200 });
    });

    modeGroup?.querySelectorAll('[data-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            mode = button.dataset.mode;
            modeGroup.querySelectorAll('[data-mode]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
            document.querySelectorAll('[data-legend]').forEach((el) => { el.hidden = el.dataset.legend !== mode; });
            const map = container.__sakukiMap;
            if (map) applyVisibility(map);
        });
    });
}
