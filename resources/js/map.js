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

                window.dispatchEvent(new CustomEvent('parcel-selected', { detail: feature.properties }));
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
        const toggleLabelsBtn = document.getElementById('toggle-labels');
        if (toggleLabelsBtn) {
            toggleLabelsBtn.addEventListener('click', () => {
                if (! map.getLayer('parcels-labels')) return;
                const visible = map.getLayoutProperty('parcels-labels', 'visibility') !== 'none';
                map.setLayoutProperty('parcels-labels', 'visibility', visible ? 'none' : 'visible');
                toggleLabelsBtn.classList.toggle('bg-surface-container', ! visible);
                toggleLabelsBtn.classList.toggle('dark:bg-white/10', ! visible);
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
                map.once('style.load', addParcelLayers);
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
                window.dispatchEvent(new CustomEvent('parcel-selected', { detail: match.properties }));
            });
        }
    }
}
