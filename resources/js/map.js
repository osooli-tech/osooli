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
                'حجة استحكام': '#4a90d9', 'مخطط': '#9b6dd6', 'الصك': '#e07b39',
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

        // Read once and reused everywhere a colour needs to differ by theme —
        // basemap style, label colours, label halo.
        const isDarkMode = document.documentElement.classList.contains('dark');

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
            style: isDarkMode
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
        let cameraPositioned = false;
        let cityFilterPopulated = false;
        // Where the selected parcel is, so it can be kept in view when the
        // details panel opens and narrows the map.
        let selectedLngLat = null;

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

            // A dot at each parcel's centroid, prominent at a wide zoom range
            // and fading out by the zoom where the real polygon is legible.
            // Without this, a parcel a few pixels across at medium zoom was
            // only findable by its number label — this makes it a deliberate,
            // visible mark that yields to the real shape as the user zooms in,
            // rather than the shape simply staying invisible until then.
            map.addSource('parcel-markers', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });

            map.addLayer({
                id: 'parcels-markers',
                type: 'circle',
                source: 'parcel-markers',
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 5, 3, 12, 7, 16, 0],
                    'circle-color': colours.parcels_fill,
                    'circle-opacity': ['interpolate', ['linear'], ['zoom'], 5, 0.9, 12, 0.8, 16, 0],
                    'circle-stroke-color': isDarkMode ? '#0d1420' : '#ffffff',
                    'circle-stroke-width': 1.5,
                },
            });

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

            // Parcel number labels. Both size and halo grow with zoom — at a
            // medium default zoom a small parcel's polygon is only a few
            // pixels across, and a full-size halo at that scale used to
            // paint the whole shape a flat white instead of the fill colour
            // showing through around a small dark parcel number.
            //
            // Text colour is theme-aware for the same reason: the fixed navy
            // '#002444' effectively disappeared against the dark-v11 basemap,
            // leaving only its white halo visible — which read as "the parcel
            // itself is white" rather than as a label.
            map.addLayer({
                id: 'parcels-labels',
                type: 'symbol',
                source: 'parcels',
                layout: {
                    'text-field': ['to-string', ['get', 'parcel_no']],
                    'text-size': ['interpolate', ['linear'], ['zoom'], 10, 8, 14, 11, 18, 15],
                    'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-allow-overlap': false,
                    'text-ignore-placement': false,
                },
                paint: {
                    'text-color': isDarkMode ? '#e8ecf5' : '#002444',
                    'text-halo-color': isDarkMode ? '#0d1420' : '#ffffff',
                    'text-halo-width': ['interpolate', ['linear'], ['zoom'], 10, 0.4, 14, 1.5],
                },
            });

            // Frames every parcel at once, from cluster to cluster across the
            // whole dataset — using each parcel's centroid rather than its
            // full polygon, so one oddly-shaped or unusually large parcel
            // can't stretch the frame further than the data actually needs.
            //
            // This used to jump to one fixed zoom on one cluster instead,
            // which hid every other cluster entirely — a real problem once a
            // production check showed parcels spread across ~300km in
            // several separate groups, not one city's worth close together.
            // The centroid-dot layer above is what makes framing everything
            // workable here: without it, a view wide enough to show every
            // cluster left individual parcels too small to see at all.
            const positionCamera = (features) => {
                if (cameraPositioned || ! features.length) return;
                const bounds = new mapboxgl.LngLatBounds();
                features.forEach((f) => {
                    const lng = f.properties?.centroid_lng;
                    const lat = f.properties?.centroid_lat;
                    if (typeof lng === 'number' && typeof lat === 'number') bounds.extend([lng, lat]);
                });
                if (! bounds.isEmpty()) {
                    map.fitBounds(bounds, { padding: 60, maxZoom: 15 });
                    cameraPositioned = true;
                }
            };

            // The centroid-dot layer's own source data, built from the same
            // centroid_lat/centroid_lng the camera centring above uses. Runs
            // every time this function's caller does — including after a
            // basemap switch, which rebuilds every source and layer from
            // scratch and would otherwise leave this one permanently empty.
            const populateMarkers = (features) => {
                if (! features.length) return;

                const points = features
                    .filter((f) => typeof f.properties?.centroid_lng === 'number' && typeof f.properties?.centroid_lat === 'number')
                    .map((f) => ({
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: [f.properties.centroid_lng, f.properties.centroid_lat] },
                        properties: f.properties,
                    }));

                map.getSource('parcel-markers')?.setData({ type: 'FeatureCollection', features: points });
            };

            const applyBounds = (data) => {
                if (! data.features?.length) return;
                allFeatures = data.features;
                positionCamera(allFeatures);
                populateMarkers(allFeatures);
                populateCityFilter(allFeatures);
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
                selectedLngLat = e.lngLat;
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

        // Filled once from the parcels actually loaded, not queried
        // separately — a city with no parcels on the map has no reason to
        // appear in a filter over the map.
        function populateCityFilter(features) {
            const select = document.getElementById('map-city-filter');
            if (! select || cityFilterPopulated) return;
            cityFilterPopulated = true;

            const cities = [...new Set(
                features.map((f) => f.properties?.city_name).filter(Boolean)
            )].sort((a, b) => a.localeCompare(b, 'ar'));

            cities.forEach((city) => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                select.appendChild(option);
            });
        }

        // Layers a "show me this on the map" filter applies to together, so
        // a hidden parcel's fill, outline and label all disappear as one.
        const FILTERABLE_LAYERS = ['parcels-fill', 'parcels-outline', 'parcels-labels', 'parcels-markers'];

        function matchesFilter(properties, type, value) {
            if (type === 'city') return properties?.city_name === value;
            if (type === 'district') return properties?.district_name === value;
            if (type === 'parcelIds') return value.includes(properties?.id);

            return true;
        }

        function clearMapFilter() {
            FILTERABLE_LAYERS.forEach((id) => {
                if (map.getLayer(id)) map.setFilter(id, null);
            });
            const select = document.getElementById('map-city-filter');
            if (select) select.value = '';
        }

        // Shared by the city dropdown, a city-portfolio card, an
        // owner-portfolio card, and the "by city" dashboard chart — every
        // one of them just needs to say what to show, not how the map shows it.
        function applyMapFilter(type, value) {
            const expression = type === 'parcelIds'
                ? ['in', ['get', 'id'], ['literal', value]]
                : ['==', ['get', type === 'city' ? 'city_name' : 'district_name'], value];

            FILTERABLE_LAYERS.forEach((id) => {
                if (map.getLayer(id)) map.setFilter(id, expression);
            });

            const select = document.getElementById('map-city-filter');
            if (select) select.value = type === 'city' ? value : '';

            const matches = allFeatures.filter((f) => matchesFilter(f.properties, type, value));
            if (matches.length) {
                const bounds = new mapboxgl.LngLatBounds();
                matches.forEach((f) => {
                    const coords = f.geometry?.coordinates;
                    if (! coords) return;
                    (f.geometry.type === 'Polygon' ? coords[0] : coords.flat(2))
                        .forEach((c) => bounds.extend(c));
                });
                if (! bounds.isEmpty()) map.fitBounds(bounds, { padding: 60, maxZoom: 16 });
            }

            document.getElementById('map')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        window.addEventListener('map-filter', (e) => {
            const { type, value } = e.detail ?? {};
            if (! type || value === undefined || value === null) return;
            applyMapFilter(type, value);
        });
        window.addEventListener('map-filter-clear', clearMapFilter);

        const cityFilterSelect = document.getElementById('map-city-filter');
        cityFilterSelect?.addEventListener('change', () => {
            cityFilterSelect.value ? applyMapFilter('city', cityFilterSelect.value) : clearMapFilter();
        });
        document.getElementById('map-filter-clear-btn')?.addEventListener('click', clearMapFilter);

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

        // ── Administrative boundaries ─────────────────────────────
        // Loaded for the current view only, at a simplification to match the
        // zoom, and only for the levels switched on. Cities and districts
        // wait until the map is close enough for them to mean anything.
        const BOUNDARIES = {
            regions: { colour: '#002444', width: 2.5, minZoom: 0, dash: [1] },
            cities: { colour: '#7a5c00', width: 1.8, minZoom: 7, dash: [3, 2] },
            districts: { colour: '#c0392b', width: 1.2, minZoom: 11, dash: [2, 2] },
        };
        const boundaryUrl = container.dataset.boundariesUrl ?? '';
        const boundariesOn = new Set();

        function addBoundaryLayers() {
            Object.entries(BOUNDARIES).forEach(([level, style]) => {
                const id = `boundary-${level}`;
                if (map.getSource(id)) return;

                map.addSource(id, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                map.addLayer({
                    id: `${id}-line`,
                    type: 'line',
                    source: id,
                    minzoom: style.minZoom,
                    layout: { visibility: boundariesOn.has(level) ? 'visible' : 'none' },
                    paint: {
                        'line-color': style.colour,
                        'line-width': style.width,
                        'line-dasharray': style.dash,
                        // Approximate city boundaries are drawn fainter than official ones.
                        'line-opacity': ['case', ['==', ['get', 'source'], 'approximate'], 0.45, 0.9],
                    },
                });
                map.addLayer({
                    id: `${id}-labels`,
                    type: 'symbol',
                    source: id,
                    minzoom: style.minZoom,
                    layout: {
                        visibility: boundariesOn.has(level) ? 'visible' : 'none',
                        'text-field': ['get', 'name'],
                        'text-size': level === 'districts' ? 11 : 12,
                        'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                        'symbol-placement': 'point',
                        'text-allow-overlap': false,
                    },
                    paint: { 'text-color': style.colour, 'text-halo-color': '#ffffff', 'text-halo-width': 1.5 },
                });
            });
        }

        let boundaryTimer = null;
        function refreshBoundaries() {
            clearTimeout(boundaryTimer);
            boundaryTimer = setTimeout(() => {
                const zoom = map.getZoom();
                const b = map.getBounds();
                const bbox = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].map((n) => n.toFixed(5)).join(',');

                boundariesOn.forEach((level) => {
                    if (zoom < BOUNDARIES[level].minZoom || ! boundaryUrl) return;
                    const url = `${boundaryUrl.replace('__LEVEL__', level)}?bbox=${bbox}&zoom=${zoom.toFixed(1)}`;
                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then((r) => r.json())
                        .then((data) => map.getSource(`boundary-${level}`)?.setData(data))
                        .catch((err) => console.error('[Sakuki] boundaries load failed:', err));
                });
            }, 250);
        }

        document.querySelectorAll('input[data-boundary]').forEach((box) => {
            box.addEventListener('change', () => {
                const level = box.dataset.boundary;
                box.checked ? boundariesOn.add(level) : boundariesOn.delete(level);
                ['line', 'labels'].forEach((part) => {
                    const id = `boundary-${level}-${part}`;
                    if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', box.checked ? 'visible' : 'none');
                });
                refreshBoundaries();
            });
        });
        map.on('moveend', refreshBoundaries);

        function addAllLayers() {
            addBoundaryLayers();
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

        // The details panel opens beside the map and narrows the container
        // without the window resizing, which is all Mapbox watches for. The
        // strip that gets cut off may hold the selected parcel, so bring it
        // back — unless a search is still flying there.
        new ResizeObserver(() => {
            map.resize();
            if (selectedLngLat && ! map.isMoving() && ! map.getBounds().contains(selectedLngLat)) {
                map.easeTo({ center: selectedLngLat });
            }
        }).observe(container);

        // Closing the panel drops the highlight with it, so the map never
        // shows a selection that has no details beside it.
        window.addEventListener('parcel-cleared', () => {
            if (selectedId !== null && map.getSource('parcels')) {
                map.setFeatureState({ source: 'parcels', id: selectedId }, { selected: false });
            }
            selectedId = null;
            selectedLngLat = null;
        });

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
            refreshBoundaries();
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
        const streetStyle = isDarkMode
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
                selectedLngLat = bounds.getCenter();
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
