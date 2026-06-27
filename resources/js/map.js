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

        map.on('load', () => {
            const geoUrl = container.dataset.geojsonUrl;
            if (! geoUrl) return;

            map.addSource('parcels', { type: 'geojson', data: geoUrl });

            map.addLayer({
                id: 'parcels-fill',
                type: 'fill',
                source: 'parcels',
                paint: {
                    'fill-color': '#68dbae',
                    'fill-opacity': 0.35,
                },
            });

            map.addLayer({
                id: 'parcels-outline',
                type: 'line',
                source: 'parcels',
                paint: {
                    'line-color': '#006c4e',
                    'line-width': 1.5,
                },
            });

            // Fit bounds to parcel data
            fetch(geoUrl)
                .then((r) => r.json())
                .then((data) => {
                    if (! data.features?.length) return;
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
                })
                .catch((err) => console.error('[Sakuki] GeoJSON load failed:', err));

            // Click: dispatch event for Alpine parcel-detail panel
            map.on('click', 'parcels-fill', (e) => {
                const props = e.features[0].properties;
                window.dispatchEvent(new CustomEvent('parcel-selected', { detail: props }));
            });

            map.on('mouseenter', 'parcels-fill', () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', 'parcels-fill', () => {
                map.getCanvas().style.cursor = '';
            });
        });
    }
}
