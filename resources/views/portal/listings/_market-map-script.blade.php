{{-- The market map: every listed parcel the owner may see, each opening a small
     card with its area, streets and place and a link to its page. Mapbox comes
     from the CDN through window.loadMapbox, like the parcel mini-map. --}}
<script>
function marketMap(collection, urlTemplate, labels) {
    return {
        init() {
            const token = @js(config('services.mapbox.token'));
            if (!token || !window.loadMapbox || !collection.features.length) return;

            window.loadMapbox()
                .then((mapboxgl) => this.draw(mapboxgl, token))
                .catch(() => console.warn('[Sakuki] mapbox-gl failed to load; the market map is unavailable but the page is not.'));
        },

        draw(mapboxgl, token) {
            mapboxgl.accessToken = token;
            const map = new mapboxgl.Map({
                container: this.$refs.map,
                style: 'mapbox://styles/mapbox/satellite-streets-v12',
                center: [45.0, 24.5],
                zoom: 5,
            });
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-left');

            map.on('load', () => {
                map.addSource('listings', { type: 'geojson', data: collection });
                map.addLayer({ id: 'listings-fill', type: 'fill', source: 'listings', paint: { 'fill-color': ['case', ['get', 'is_mine'], '#006c4e', '#c9a84c'], 'fill-opacity': 0.45 } });
                map.addLayer({ id: 'listings-line', type: 'line', source: 'listings', paint: { 'line-color': '#ffe08f', 'line-width': 2 } });

                const bounds = new mapboxgl.LngLatBounds();
                const extend = (coords) => (typeof coords[0] === 'number' ? bounds.extend(coords) : coords.forEach(extend));
                collection.features.forEach((f) => extend(f.geometry.coordinates));
                map.fitBounds(bounds, { padding: 60, maxZoom: 16, duration: 0 });

                map.on('mouseenter', 'listings-fill', () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', 'listings-fill', () => { map.getCanvas().style.cursor = ''; });
                map.on('click', 'listings-fill', (e) => {
                    const p = e.features[0].properties;
                    new mapboxgl.Popup({ closeButton: true, maxWidth: '260px' })
                        .setLngLat(e.lngLat)
                        .setDOMContent(this.card(p))
                        .addTo(map);
                });
            });
        },

        // Built with DOM nodes, never innerHTML, so nothing in the data is read as markup.
        card(p) {
            const streets = typeof p.streets === 'string' ? JSON.parse(p.streets) : (p.streets || []);
            const box = document.createElement('div');
            box.dir = document.documentElement.dir;
            box.style.cssText = 'font-family: inherit; font-size: 13px; color: #0b1c30; min-width: 180px;';
            const line = (label, value) => {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex; justify-content:space-between; gap:12px; padding:2px 0;';
                const k = document.createElement('span'); k.style.color = '#5b6b82'; k.textContent = label;
                const v = document.createElement('strong'); v.textContent = value;
                row.append(k, v);
                box.append(row);
            };
            if (p.is_mine === true || p.is_mine === 'true') {
                const tag = document.createElement('div');
                tag.style.cssText = 'font-weight:700; color:#006c4e; margin-bottom:4px;';
                tag.textContent = labels.yours;
                box.append(tag);
            }
            line(labels.area, p.area ? Number(p.area).toLocaleString() + ' ' + labels.sqm : '—');
            line(labels.streets, streets.length
                ? streets.map((s) => s.side + (s.width ? ' ' + s.width + ' ' + labels.metre : '')).join('، ')
                : labels.none);
            if (p.asset_type) line(labels.type, p.asset_type);
            if (p.place) line(labels.place, p.place);
            const link = document.createElement('a');
            link.href = urlTemplate.replace('__ID__', p.id);
            link.textContent = labels.view;
            link.style.cssText = 'display:block; margin-top:8px; font-weight:600; color:#006c4e;';
            box.append(link);
            return box;
        },
    };
}
</script>
