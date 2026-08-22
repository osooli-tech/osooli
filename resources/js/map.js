// mapbox-gl is loaded via CDN in dashboard.blade.php to avoid Vite WebWorker bundling issues.
// window.mapboxgl is available before this script runs.

const container = document.getElementById('sakuki-map');
if (! container) {
    // Not on a page that has the map.
} else {
    const mapboxgl = window.mapboxgl;
    const token = container.dataset.token ?? '';

    if (! token) {
        console.warn('[Sakuki] MAPBOX_TOKEN is not set. Add it to .env to enable the map.');
    } else {
        mapboxgl.accessToken = token;

        const map = new mapboxgl.Map({
            container: 'sakuki-map',
            style: document.documentElement.classList.contains('dark')
                ? 'mapbox://styles/mapbox/dark-v11'
                : 'mapbox://styles/mapbox/light-v11',
            center: [45.0, 24.0],
            zoom: 5,
        });

        map.addControl(new mapboxgl.NavigationControl(), 'bottom-left');
        map.addControl(new mapboxgl.ScaleControl(), 'bottom-right');

        let allFeatures = [];
        let hoveredId = null;
        let selectedId = null;

        // Extract the parcel's corner coordinates from its polygon geometry.
        // Returns [{ lat, lng }] for the exterior ring, dropping the closing
        // point that duplicates the first.
        function extractCorners(geometry) {
            if (! geometry) return [];
            const ring = geometry.type === 'Polygon'
                ? geometry.coordinates[0]
                : (geometry.coordinates[0]?.[0] ?? []);
            const points = ring.slice(0, -1);

            return points.map(([lng, lat]) => ({ lat, lng }));
        }

        function addParcelLayers() {
            const geoUrl = container.dataset.geojsonUrl;
            if (! geoUrl) return;

            map.addSource('parcels', { type: 'geojson', data: geoUrl, promoteId: 'id' });

            map.addLayer({
                id: 'parcels-fill',
                type: 'fill',
                source: 'parcels',
                paint: {
                    'fill-color': '#00b386',
                    'fill-opacity': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false], 0.55,
                        ['boolean', ['feature-state', 'hover'], false], 0.5,
                        0.4,
                    ],
                },
            });

            map.addLayer({
                id: 'parcels-outline',
                type: 'line',
                source: 'parcels',
                paint: {
                    'line-color': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false], '#c9a84c',
                        '#39ff14',
                    ],
                    'line-width': [
                        'case',
                        ['boolean', ['feature-state', 'selected'], false], 3,
                        1.75,
                    ],
                },
            });

            // Parcel number labels
            map.addLayer({
                id: 'parcels-labels',
                type: 'symbol',
                source: 'parcels',
                layout: {
                    'text-field': ['to-string', ['get', 'parcel_no']],
                    'text-size': 11,
                    'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-allow-overlap': false,
                    'text-ignore-placement': false,
                },
                paint: {
                    'text-color': '#002444',
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1.5,
                },
            });

            // Fit bounds to parcel data (first load only — allFeatures already cached after that)
            const applyBounds = (data) => {
                if (! data.features?.length) return;
                allFeatures = data.features;
                const bounds = new mapboxgl.LngLatBounds();
                data.features.forEach((f) => {
                    const coords = f.geometry?.coordinates;
                    if (! coords) return;
                    (f.geometry.type === 'Polygon' ? coords[0] : coords.flat(2))
                        .forEach((c) => bounds.extend(c));
                });
                if (! bounds.isEmpty()) {
                    map.fitBounds(bounds, { padding: 60, maxZoom: 17 });
                }
            };

            if (allFeatures.length) {
                applyBounds({ features: allFeatures });
            } else {
                fetch(geoUrl)
                    .then((r) => r.json())
                    .then(applyBounds)
                    .catch((err) => console.error('[Sakuki] GeoJSON load failed:', err));
            }

            // Click: highlight the parcel and dispatch event for Alpine parcel-detail panel
            map.on('click', 'parcels-fill', (e) => {
                const feature = e.features[0];
                if (selectedId !== null) {
                    map.setFeatureState({ source: 'parcels', id: selectedId }, { selected: false });
                }
                selectedId = feature.id;
                map.setFeatureState({ source: 'parcels', id: selectedId }, { selected: true });

                window.dispatchEvent(new CustomEvent('parcel-selected', {
                    detail: { ...feature.properties, corners: extractCorners(feature.geometry) },
                }));
            });

            map.on('mousemove', 'parcels-fill', (e) => {
                if (! e.features.length) return;
                if (hoveredId !== null && hoveredId !== e.features[0].id) {
                    map.setFeatureState({ source: 'parcels', id: hoveredId }, { hover: false });
                }
                hoveredId = e.features[0].id;
                map.setFeatureState({ source: 'parcels', id: hoveredId }, { hover: true });
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'parcels-fill', () => {
                if (hoveredId !== null) {
                    map.setFeatureState({ source: 'parcels', id: hoveredId }, { hover: false });
                }
                hoveredId = null;
                map.getCanvas().style.cursor = '';
            });
        }

        map.on('load', addParcelLayers);

        // ── Layer controls ──────────────────────────────────────

        // Each colouring is a Mapbox match expression plus the legend that
        // explains it, so the two can never drift apart.
        const BASE_COLOUR = '#00b386';
        const OTHER_COLOUR = '#8a8f98';

        const COLOUR_MODES = {
            none: null,
            deed_status: {
                property: 'deed_status',
                stops: [['محدث', '#00b386'], ['قديم', '#d9534f']],
            },
            asset_type: {
                property: 'asset_type',
                stops: [
                    ['أرض', '#00b386'],
                    ['فيلا', '#c9a84c'],
                    ['عمارة', '#4a90d9'],
                    ['شقة', '#9b6dd6'],
                    ['مستودع', '#e07b39'],
                ],
            },
            priced: {
                property: 'is_priced',
                stops: [[true, '#00b386'], [false, '#8a8f98']],
                labels: { true: 'مسعّرة', false: 'غير مسعّرة' },
            },
        };

        let colourMode = 'none';

        function fillColourExpression(mode) {
            const config = COLOUR_MODES[mode];
            if (! config) return BASE_COLOUR;

            return [
                'match',
                ['to-string', ['get', config.property]],
                ...config.stops.flatMap(([value, colour]) => [String(value), colour]),
                OTHER_COLOUR,
            ];
        }

        function renderLegend(mode) {
            const box = document.getElementById('map-legend');
            const items = document.getElementById('map-legend-items');
            if (! box || ! items) return;

            const config = COLOUR_MODES[mode];
            if (! config) {
                box.classList.add('hidden');
                items.innerHTML = '';
                return;
            }

            box.classList.remove('hidden');
            items.innerHTML = config.stops.map(([value, colour]) => {
                const label = config.labels?.[String(value)] ?? String(value);
                return `<div class="flex items-center gap-2 text-xs text-on-surface dark:text-white">
                            <span class="w-3 h-3 rounded-sm shrink-0" style="background:${colour}"></span>
                            <span>${label}</span>
                        </div>`;
            }).join('');
        }

        function applyColourMode() {
            if (! map.getLayer('parcels-fill')) return;
            map.setPaintProperty('parcels-fill', 'fill-color', fillColourExpression(colourMode));
            renderLegend(colourMode);
        }

        document.querySelectorAll('input[name="parcel-colour"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                colourMode = radio.value;
                applyColourMode();
            });
        });

        // Per-layer visibility, remembered so a basemap switch restores it.
        const hiddenLayers = new Set();

        document.querySelectorAll('input[data-layer]').forEach((box) => {
            box.addEventListener('change', () => {
                const id = box.dataset.layer;
                box.checked ? hiddenLayers.delete(id) : hiddenLayers.add(id);
                if (map.getLayer(id)) {
                    map.setLayoutProperty(id, 'visibility', box.checked ? 'visible' : 'none');
                }
            });
        });

        // Switching basemap rebuilds every layer, so the colouring and the
        // hidden set have to be re-applied on top of the fresh style.
        function restoreLayerState() {
            applyColourMode();
            hiddenLayers.forEach((id) => {
                if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', 'none');
            });
        }

        const toggleBasemapBtn = document.getElementById('toggle-basemap');
        const basemapLabel = document.getElementById('basemap-label');
        const streetStyle = document.documentElement.classList.contains('dark')
            ? 'mapbox://styles/mapbox/dark-v11'
            : 'mapbox://styles/mapbox/light-v11';
        const satelliteStyle = 'mapbox://styles/mapbox/satellite-streets-v12';
        let onSatellite = false;

        if (toggleBasemapBtn && basemapLabel) {
            toggleBasemapBtn.addEventListener('click', () => {
                onSatellite = ! onSatellite;
                map.setStyle(onSatellite ? satelliteStyle : streetStyle);
                map.once('style.load', () => {
                    addParcelLayers();
                    restoreLayerState();
                });
                basemapLabel.textContent = onSatellite
                    ? basemapLabel.dataset.streetLabel ?? 'خريطة الشوارع'
                    : basemapLabel.dataset.satelliteLabel ?? 'قمر صناعي';
            });
        }

        // ── Search box ──────────────────────────────────────────
        const searchInput = document.getElementById('map-search');
        if (searchInput) {
            searchInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const term = searchInput.value.trim();
                if (! term) return;

                const match = allFeatures.find((f) => {
                    const p = f.properties ?? {};
                    return String(p.parcel_no ?? '').includes(term) || String(p.deed_no ?? '').includes(term);
                });

                if (! match) return;

                if (selectedId !== null) {
                    map.setFeatureState({ source: 'parcels', id: selectedId }, { selected: false });
                }
                selectedId = match.properties.id;
                map.setFeatureState({ source: 'parcels', id: selectedId }, { selected: true });

                const coords = match.geometry?.coordinates;
                const flat = match.geometry?.type === 'Polygon' ? coords[0] : coords.flat(2);
                const bounds = new mapboxgl.LngLatBounds();
                flat.forEach((c) => bounds.extend(c));
                map.fitBounds(bounds, { padding: 120, maxZoom: 18 });
                window.dispatchEvent(new CustomEvent('parcel-selected', {
                    detail: { ...match.properties, corners: extractCorners(match.geometry) },
                }));
            });
        }
    }
}
