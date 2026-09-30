{{-- Mapbox loader plus the parcelMiniMap Alpine component. Shared by the
     parcel page and the digital twin so the map behaves identically on both.
     Loaded from the CDN rather than Vite, which cannot bundle its WebWorker. --}}
{{-- Load mapbox-gl from CDN — same approach as dashboard.blade.php to avoid Vite WebWorker bundling issues --}}
<script>
function parcelMiniMap(geojson, neighboursJson, parcelNo, opts = {}) {
    return {
        init() {
            if (!geojson || !window.loadMapbox) return;
            const token = '{{ config('services.mapbox.token') }}';
            if (!token) return;

            window.loadMapbox()
                .then((mapboxgl) => this.initMap(mapboxgl, token))
                .catch(() => console.warn('[Sakuki] mapbox-gl failed to load; the map is unavailable but the page is not.'));
        },

        initMap(mapboxgl, token) {
            mapboxgl.accessToken = token;
            // A page theme toggle updates it; label colours read it on every (re)add.
            let isDark = document.documentElement.classList.contains('dark');
            const streetStyle = () => (isDark ? 'mapbox://styles/mapbox/dark-v11' : 'mapbox://styles/mapbox/light-v11');
            const satelliteStyle = 'mapbox://styles/mapbox/satellite-streets-v12';

            // Aerial imagery is the default background here — it shows the
            // parcel against the real terrain rather than a generic basemap.
            const map = new mapboxgl.Map({
                container: 'parcel-mini-map',
                style: satelliteStyle,
                center: [45.0, 24.5],
                zoom: 12,
                attributionControl: false,
            });

            map.addControl(new mapboxgl.NavigationControl({ showCompass: true, showZoom: true }), 'bottom-left');
            const basemapControl = basemapToggleControl(streetStyle, satelliteStyle);
            map.addControl(basemapControl, 'top-left');

            // The aerial background has no dark variant; only the street one follows the theme.
            window.addEventListener('sakuki:theme-changed', ({ detail }) => {
                isDark = detail.dark;
                if (basemapControl.onSatellite()) return;
                map.setStyle(streetStyle());
            });

            function addLayers() {
                // Runs on every style load — the first one and each swap, which wipes layers.
                if (map.getSource('parcel')) return;
                const geom = JSON.parse(geojson);
                const feature = { type: 'Feature', geometry: geom, properties: { parcel_no: parcelNo } };

                // Neighbours first so they sit beneath the parcel itself
                let neighbours = null;
                try { neighbours = neighboursJson ? JSON.parse(neighboursJson) : null; } catch (e) { neighbours = null; }

                if (neighbours && neighbours.features && neighbours.features.length) {
                    map.addSource('neighbours', { type: 'geojson', data: neighbours });
                    map.addLayer({ id: 'neighbours-fill', type: 'fill', source: 'neighbours',
                        paint: { 'fill-color': '#94a3b8', 'fill-opacity': 0.12 } });
                    map.addLayer({ id: 'neighbours-outline', type: 'line', source: 'neighbours',
                        paint: { 'line-color': '#94a3b8', 'line-width': 1, 'line-opacity': 0.5 } });
                    map.addLayer({ id: 'neighbours-labels', type: 'symbol', source: 'neighbours',
                        layout: {
                            'text-field': ['to-string', ['get', 'parcel_no']],
                            'text-size': 10,
                            'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                        },
                        paint: {
                            'text-color': isDark ? '#94a3b8' : '#64748b',
                            'text-halo-color': isDark ? '#0f172a' : '#ffffff',
                            'text-halo-width': 1.2,
                            'text-opacity': 0.75,
                        } });
                }

                map.addSource('parcel', { type: 'geojson', data: feature });
                map.addLayer({ id: 'parcel-fill', type: 'fill', source: 'parcel',
                    paint: { 'fill-color': '#006c4e', 'fill-opacity': 0.35 } });
                map.addLayer({ id: 'parcel-outline', type: 'line', source: 'parcel',
                    paint: { 'line-color': '#39ff14', 'line-width': 2.5 } });
                map.addLayer({ id: 'parcel-label', type: 'symbol', source: 'parcel',
                    layout: {
                        'text-field': ['to-string', ['get', 'parcel_no']],
                        'text-size': 14,
                        'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                        'text-allow-overlap': true,
                    },
                    paint: {
                        'text-color': '#002444',
                        'text-halo-color': '#ffffff',
                        'text-halo-width': 2,
                    } });

                // Fit to parcel bounds
                const coords = geom.type === 'MultiPolygon'
                    ? geom.coordinates.flat(2)
                    : geom.coordinates.flat(1);
                const lngs = coords.map(c => c[0]), lats = coords.map(c => c[1]);
                // Portal only (opts.threeD): the parcel stands at its type's
                // height and colour (opts.massing) among flat neighbours, and
                // the camera tilts once framed.
                if (opts.threeD) {
                    if (map.getSource('neighbours')) {
                        map.addLayer({ id: 'neighbours-3d', type: 'fill-extrusion', source: 'neighbours',
                            paint: { 'fill-extrusion-color': '#94a3b8', 'fill-extrusion-height': 0.5, 'fill-extrusion-opacity': 0.45 } });
                    }
                    map.addLayer({ id: 'parcel-3d', type: 'fill-extrusion', source: 'parcel',
                        paint: { 'fill-extrusion-color': opts.massing?.color ?? '#c9a84c', 'fill-extrusion-height': opts.massing?.height ?? 28,
                                 'fill-extrusion-opacity': 0.92, 'fill-extrusion-vertical-gradient': true } });
                }

                map.fitBounds(
                    [[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]],
                    { padding: 40, maxZoom: opts.threeD ? 16.6 : 17 }
                );
                if (opts.threeD) {
                    map.once('moveend', () => map.easeTo({ pitch: 55, bearing: -22, duration: 1800 }));
                }
            }

            map.on('style.load', addLayers);
        }
    };
}

// A small Mapbox IControl that switches the mini-map between the aerial
// (default) background and the plain street style; the map's 'style.load'
// handler puts the parcel layers back after each swap.
function basemapToggleControl(streetStyle, satelliteStyle) {
    let map;
    let onSatellite = true;
    let button;

    const icon = () => onSatellite ? 'map' : 'satellite_alt';
    const title = () => onSatellite ? '{{ __('parcels.show_streets') }}' : '{{ __('parcels.show_satellite') }}';

    return {
        onAdd(mapInstance) {
            map = mapInstance;
            const container = document.createElement('div');
            container.className = 'mapboxgl-ctrl mapboxgl-ctrl-group';
            button = document.createElement('button');
            button.type = 'button';
            button.title = title();
            button.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;line-height:29px;color:#333;">${icon()}</span>`;
            button.addEventListener('click', () => {
                onSatellite = !onSatellite;
                map.setStyle(onSatellite ? satelliteStyle : streetStyle());
                button.title = title();
                button.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;line-height:29px;color:#333;">${icon()}</span>`;
            });
            container.appendChild(button);
            return container;
        },
        onSatellite: () => onSatellite,
        onRemove() {
            button?.parentNode?.parentNode?.removeChild(button.parentNode);
            map = undefined;
        },
    };
}
</script>
