// mapbox-gl comes from the CDN — Vite cannot bundle its WebWorker. The tag is
// deferred and may fail outright on a network that cannot reach api.mapbox.com,
// so nothing here may assume window.mapboxgl exists.

const container = document.getElementById('sakuki-map');
if (! container) {
    // Not on a page that has the map.
} else {
    const token = container.dataset.token ?? '';
    const canEditColours = container.dataset.canEditColors === '1';
    const coloursUpdateUrl = container.dataset.colorsUpdateUrl ?? '';
    const baseColourLabels = JSON.parse(container.dataset.baseColorLabels || '{}');

    // Mirrors App\Models\MapAppearanceSetting::DEFAULTS — used only if the
    // server didn't render a config (e.g. the data attribute is missing).
    const DEFAULT_COLOURS = {
        parcels_fill: '#00b386',
        parcels_outline: '#39ff14',
        projects_fill: '#c9a84c',
        buildings_fill: '#4a90d9',
        colour_modes: {
            deed_status: { 'محدث': '#00b386', 'قديم': '#d9534f' },
            asset_type: {
                'أرض': '#00b386', 'فيلا': '#c9a84c', 'عمارة': '#4a90d9',
                'شقة': '#9b6dd6', 'مستودع': '#e07b39',
            },
            priced: { true: '#00b386', false: '#8a8f98' },
            fall_in: {
                'مخطط زراعي': '#00b386', 'مخطط بلدية': '#d9534f', 'طلبات احكام': '#c9a84c',
                'حجة استحكام': '#4a90d9', 'مخطط': '#9b6dd6',
            },
        },
    };

    // The client's own colour choices — editable live from the layers panel
    // when canEditColours is true, persisted via coloursUpdateUrl on save.
    let colours;
    try {
        colours = JSON.parse(container.dataset.colors || 'null') ?? DEFAULT_COLOURS;
    } catch {
        colours = DEFAULT_COLOURS;
    }

    if (! token) {
        console.warn('[Sakuki] MAPBOX_TOKEN is not set. Add it to .env to enable the map.');
    } else if (! window.loadMapbox) {
        console.warn('[Sakuki] mapbox loader not available.');
    } else {
        window.loadMapbox().then((mapboxgl) => {
        mapboxgl.accessToken = token;

        // Without this, Mapbox GL renders Arabic (and other RTL scripts) as
        // isolated, unjoined letter forms instead of properly shaped text —
        // harmless while labels were numeric (parcel_no), but the project/
        // building name labels made it visible.
        if (! mapboxgl.getRTLTextPluginStatus || mapboxgl.getRTLTextPluginStatus() === 'unavailable') {
            mapboxgl.setRTLTextPlugin(
                'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-rtl-text/v0.3.0/mapbox-gl-rtl-text.js',
                true
            );
        }

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
                    'fill-color': colours.parcels_fill,
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
                        colours.parcels_outline,
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

        // Project zones and building footprints are a pure identification
        // layer — name/shape only, no click-through detail panel like parcels.
        function addDisplayLayer(id, url, fillColour) {
            if (! url || map.getSource(id)) return;

            map.addSource(id, { type: 'geojson', data: url });

            map.addLayer({
                id: `${id}-fill`,
                type: 'fill',
                source: id,
                paint: { 'fill-color': fillColour, 'fill-opacity': 0.25 },
            });

            map.addLayer({
                id: `${id}-outline`,
                type: 'line',
                source: id,
                paint: { 'line-color': fillColour, 'line-width': 1.5 },
            });

            map.addLayer({
                id: `${id}-labels`,
                type: 'symbol',
                source: id,
                layout: {
                    'text-field': ['to-string', ['get', 'name']],
                    'text-size': 10,
                    'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-allow-overlap': false,
                },
                paint: {
                    'text-color': fillColour,
                    'text-halo-color': '#ffffff',
                    'text-halo-width': 1.5,
                },
            });

            map.on('click', `${id}-fill`, (e) => {
                const p = e.features[0].properties;
                new mapboxgl.Popup()
                    .setLngLat(e.lngLat)
                    .setHTML(`<strong>${p.name ?? ''}</strong>${p.code ? `<br>${p.code}` : ''}`)
                    .addTo(map);
            });
            map.on('mouseenter', `${id}-fill`, () => { map.getCanvas().style.cursor = 'pointer'; });
            map.on('mouseleave', `${id}-fill`, () => { map.getCanvas().style.cursor = ''; });
        }

        function addAllLayers() {
            addParcelLayers();
            addDisplayLayer('projects', container.dataset.projectsUrl, colours.projects_fill);
            addDisplayLayer('buildings', container.dataset.buildingsUrl, colours.buildings_fill);
        }

        // Re-applies a display layer's colour after a client edit — same
        // three paint properties addDisplayLayer sets when the layer is born.
        function setDisplayLayerColour(id, colour) {
            if (! map.getLayer(`${id}-fill`)) return;
            map.setPaintProperty(`${id}-fill`, 'fill-color', colour);
            map.setPaintProperty(`${id}-outline`, 'line-color', colour);
            map.setPaintProperty(`${id}-labels`, 'text-color', colour);
        }

        map.on('load', addAllLayers);

        // ── Layer controls ──────────────────────────────────────

        // Each colouring is a Mapbox match expression plus the legend that
        // explains it, so the two can never drift apart. The stops themselves
        // come from `colours.colour_modes` (client-editable), not a constant —
        // only the Mapbox feature property and the priced mode's Arabic
        // labels are fixed per mode.
        const OTHER_COLOUR = '#8a8f98';

        const COLOUR_MODE_META = {
            none: null,
            deed_status: { property: 'deed_status' },
            asset_type: { property: 'asset_type' },
            priced: { property: 'is_priced', labels: { true: 'مسعّرة', false: 'غير مسعّرة' } },
            fall_in: { property: 'fall_in' },
        };

        let colourMode = 'none';

        function fillColourExpression(mode) {
            const meta = COLOUR_MODE_META[mode];
            if (! meta) return colours.parcels_fill;

            const stops = Object.entries(colours.colour_modes[mode] ?? {});

            return [
                'match',
                ['to-string', ['get', meta.property]],
                ...stops.flatMap(([value, colour]) => [value, colour]),
                OTHER_COLOUR,
            ];
        }

        function renderLegend(mode) {
            const box = document.getElementById('map-legend');
            const items = document.getElementById('map-legend-items');
            if (! box || ! items) return;

            const meta = COLOUR_MODE_META[mode];
            if (! meta) {
                box.classList.add('hidden');
                items.innerHTML = '';
                return;
            }

            box.classList.remove('hidden');
            items.innerHTML = '';

            Object.entries(colours.colour_modes[mode] ?? {}).forEach(([value, colour]) => {
                const label = meta.labels?.[value] ?? value;
                const row = document.createElement('div');
                row.className = 'flex items-center gap-2 text-xs text-on-surface dark:text-white';

                if (canEditColours) {
                    row.innerHTML = `<input type="color" value="${colour}" class="w-5 h-5 rounded-sm shrink-0 border-0 cursor-pointer bg-transparent p-0">
                                      <span>${label}</span>`;
                    row.querySelector('input').addEventListener('input', (e) => {
                        colours.colour_modes[mode][value] = e.target.value;
                        applyColourMode();
                    });
                } else {
                    row.innerHTML = `<span class="w-3 h-3 rounded-sm shrink-0" style="background:${colour}"></span>
                                      <span>${label}</span>`;
                }

                items.appendChild(row);
            });
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
        // 'projects-fill'/'buildings-fill' each represent a whole display
        // group (fill + outline + labels) toggled together by one checkbox.
        const DISPLAY_LAYER_GROUPS = {
            'projects-fill': ['projects-fill', 'projects-outline', 'projects-labels'],
            'buildings-fill': ['buildings-fill', 'buildings-outline', 'buildings-labels'],
        };
        const hiddenLayers = new Set();

        document.querySelectorAll('input[data-layer]').forEach((box) => {
            box.addEventListener('change', () => {
                const id = box.dataset.layer;
                box.checked ? hiddenLayers.delete(id) : hiddenLayers.add(id);
                const ids = DISPLAY_LAYER_GROUPS[id] ?? [id];
                ids.forEach((layerId) => {
                    if (map.getLayer(layerId)) {
                        map.setLayoutProperty(layerId, 'visibility', box.checked ? 'visible' : 'none');
                    }
                });
            });
        });

        // Switching basemap rebuilds every layer, so the colouring and the
        // hidden set have to be re-applied on top of the fresh style.
        function restoreLayerState() {
            applyColourMode();
            hiddenLayers.forEach((id) => {
                (DISPLAY_LAYER_GROUPS[id] ?? [id]).forEach((layerId) => {
                    if (map.getLayer(layerId)) map.setLayoutProperty(layerId, 'visibility', 'none');
                });
            });
        }

        // ── Colour customisation (roles.manage only — see the @can guard
        // in dashboard.blade.php) ────────────────────────────────────────
        function applyBaseColour(key, value) {
            if (key === 'parcels_fill') {
                applyColourMode();
            } else if (key === 'parcels_outline' && map.getLayer('parcels-outline')) {
                map.setPaintProperty('parcels-outline', 'line-color', [
                    'case', ['boolean', ['feature-state', 'selected'], false], '#c9a84c', value,
                ]);
            } else if (key === 'projects_fill') {
                setDisplayLayerColour('projects', value);
            } else if (key === 'buildings_fill') {
                setDisplayLayerColour('buildings', value);
            }
        }

        function buildBaseColourPickers() {
            const wrap = document.getElementById('base-color-pickers');
            if (! wrap) return;

            wrap.innerHTML = '';
            Object.entries(baseColourLabels).forEach(([key, label]) => {
                const row = document.createElement('label');
                row.className = 'flex items-center gap-2 px-1.5 py-1 rounded-lg text-xs text-on-surface dark:text-white';
                row.innerHTML = `<input type="color" value="${colours[key]}" data-base-colour="${key}"
                                         class="w-5 h-5 rounded-sm shrink-0 border-0 cursor-pointer bg-transparent p-0">
                                  <span>${label}</span>`;
                row.querySelector('input').addEventListener('input', (e) => {
                    colours[key] = e.target.value;
                    applyBaseColour(key, e.target.value);
                });
                wrap.appendChild(row);
            });
        }

        if (canEditColours) {
            buildBaseColourPickers();

            const saveBtn = document.getElementById('save-map-colors');
            const resetBtn = document.getElementById('reset-map-colors');
            const statusEl = document.getElementById('save-map-colors-status');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const showColourStatus = (message, isError) => {
                if (! statusEl) return;
                statusEl.textContent = message;
                statusEl.className = `text-[10px] ${isError ? 'text-error' : 'text-secondary'}`;
                setTimeout(() => statusEl.classList.add('hidden'), 2500);
            };

            const persistColours = (body) => {
                if (! coloursUpdateUrl || ! csrfToken) return;
                fetch(coloursUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                })
                    .then((r) => { if (! r.ok) throw new Error('save failed'); return r.json(); })
                    .then((data) => {
                        colours = data.colors;
                        buildBaseColourPickers();
                        applyColourMode();
                        setDisplayLayerColour('projects', colours.projects_fill);
                        setDisplayLayerColour('buildings', colours.buildings_fill);
                        showColourStatus(saveBtn?.dataset.savedLabel ?? 'Saved', false);
                    })
                    .catch(() => showColourStatus(saveBtn?.dataset.failedLabel ?? 'Save failed', true));
            };

            saveBtn?.addEventListener('click', () => persistColours(colours));
            resetBtn?.addEventListener('click', () => persistColours({}));
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
                    addAllLayers();
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
        }).catch(() => {
            console.warn('[Sakuki] mapbox-gl failed to load; the map is unavailable but the page is not.');
        });
    }
}
