@extends('layouts.app')

@section('title', __('listings.staff_title'))
@section('page-title', __('listings.staff_title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span>{{ __('listings.staff_title') }}</span>
@endsection

@push('scripts')
<script>
{{-- The review map: each listing coloured by status, a thick ring where buyers wait; a click opens it in the Livewire page. --}}
function listingsReviewMap(collection, labels) {
    return {
        init() {
            const token = @js(config('services.mapbox.token'));
            if (!token || !window.loadMapbox) return;
            window.loadMapbox()
                .then((mapboxgl) => this.draw(mapboxgl, token))
                .catch(() => console.warn('[Sakuki] mapbox-gl failed to load; the listings map is unavailable but the page is not.'));
        },

        draw(mapboxgl, token) {
            mapboxgl.accessToken = token;
            const dark = document.documentElement.classList.contains('dark');
            const map = new mapboxgl.Map({
                container: this.$refs.map,
                style: dark ? 'mapbox://styles/mapbox/dark-v11' : 'mapbox://styles/mapbox/light-v11',
                center: [45.0, 24.5],
                zoom: 5,
            });
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-left');

            const colour = ['match', ['get', 'status'],
                'pending', '#c9a84c', 'approved', '#006c4e', 'rejected', '#ba1a1a', '#74777f'];

            map.on('load', () => {
                map.addSource('listings', { type: 'geojson', data: collection });
                map.addLayer({ id: 'listings-fill', type: 'fill', source: 'listings', paint: { 'fill-color': colour, 'fill-opacity': 0.45 } });
                map.addLayer({ id: 'listings-line', type: 'line', source: 'listings', paint: {
                    'line-color': ['case', ['>', ['get', 'new_inquiries'], 0], '#ffdad6', colour],
                    'line-width': ['case', ['>', ['get', 'new_inquiries'], 0], 4, 1.5],
                } });

                const bounds = new mapboxgl.LngLatBounds();
                const extend = (c) => (typeof c[0] === 'number' ? bounds.extend(c) : c.forEach(extend));
                collection.features.forEach((f) => extend(f.geometry.coordinates));
                map.fitBounds(bounds, { padding: 50, maxZoom: 16, duration: 0 });

                const popup = new mapboxgl.Popup({ closeButton: false, closeOnClick: false });
                map.on('mousemove', 'listings-fill', (e) => {
                    map.getCanvas().style.cursor = 'pointer';
                    const p = e.features[0].properties;
                    const box = document.createElement('div');
                    box.style.cssText = 'font-size:12px; color:#0b1c30;';
                    box.textContent = labels.parcel + ' ' + p.parcel_no + ' — ' + p.status_label
                        + (p.new_inquiries > 0 ? ' · ' + labels.inquiries + ' ' + p.new_inquiries : '');
                    popup.setLngLat(e.lngLat).setDOMContent(box).addTo(map);
                });
                map.on('mouseleave', 'listings-fill', () => { map.getCanvas().style.cursor = ''; popup.remove(); });
                map.on('click', 'listings-fill', (e) => this.$wire.openListing(Number(e.features[0].properties.id)));
            });
        },
    };
}
</script>
@endpush

@section('content')
    <div class="mb-5">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('listings.staff_subtitle') }}</p>
    </div>

    <livewire:listings.listing-index />
@endsection
