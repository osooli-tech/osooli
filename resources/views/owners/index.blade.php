@extends('layouts.app')

@section('title', __('owners.title'))
@section('page-title', __('owners.title'))

@section('breadcrumb')
    <span class="material-symbols-outlined text-[12px]">/</span>
    <span>{{ __('owners.title') }}</span>
@endsection

@section('content')
@can('parcels.view')
    <div class="space-y-5">

        <livewire:owners.owner-index />

        {{-- Owner parcels map — filters to a single owner when one is picked in the table --}}
        <div x-data="ownersMap()" x-init="init()"
             class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl shadow-sm
                    border border-outline-variant dark:border-white/10 overflow-hidden">

            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4
                        border-b border-outline-variant dark:border-white/10">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-[18px] text-secondary"
                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">map</span>
                    <h3 class="text-sm font-semibold text-on-surface dark:text-white truncate">
                        <span x-show="! selectedOwnerName">{{ __('owners.map_all_parcels') }}</span>
                        <span x-show="selectedOwnerName" x-cloak>
                            {{ __('owners.map_parcels_of') }} <span x-text="selectedOwnerName"></span>
                        </span>
                    </h3>
                    <span x-show="visibleCount !== null" x-cloak
                          class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                 bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90"
                          x-text="visibleCount"></span>
                </div>

                <button type="button" x-show="selectedOwnerId" x-cloak @click="showAll()"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-xl
                               border border-outline-variant dark:border-white/10
                               text-on-surface-variant dark:text-on-primary-container
                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-[15px]">filter_alt_off</span>
                    {{ __('owners.map_show_all') }}
                </button>
            </div>

            @if (config('services.mapbox.token'))
                <div id="owners-map" class="w-full" style="height: 520px;"></div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 py-24
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[40px] opacity-30">map</span>
                    <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
                </div>
            @endif
        </div>

    </div>
@else
    <div class="flex flex-col items-center justify-center py-32 gap-4
                text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
        <p class="text-sm">{{ __('permissions.unauthorized') }}</p>
    </div>
@endcan
@endsection

@push('scripts')
{{-- mapbox-gl from CDN — same approach as the dashboard to avoid Vite WebWorker bundling issues --}}
<link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet" />
<script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
<script>
function ownersMap() {
    return {
        map: null,
        mapReady: false,
        allFeatures: [],
        selectedOwnerId: null,
        selectedOwnerName: '',
        visibleCount: null,

        init() {
            // Data is fetched independently of the map so filtering still works
            // even if the map style is slow to load.
            fetch(@js(route('geo.parcels')))
                .then((r) => r.json())
                .then((data) => {
                    this.allFeatures = data.features ?? [];
                    this.apply();
                })
                .catch((err) => console.error('[Sakuki] GeoJSON load failed:', err));

            const token = @js(config('services.mapbox.token'));
            if (! token || ! window.mapboxgl) return;

            mapboxgl.accessToken = token;
            this.map = new mapboxgl.Map({
                container: 'owners-map',
                style: document.documentElement.classList.contains('dark')
                    ? 'mapbox://styles/mapbox/dark-v11'
                    : 'mapbox://styles/mapbox/light-v11',
                center: [45.0, 24.5],
                zoom: 5,
            });

            this.map.addControl(new mapboxgl.NavigationControl(), 'bottom-left');
            this.map.addControl(new mapboxgl.ScaleControl(), 'bottom-right');

            this.map.on('load', () => {
                this.map.addSource('parcels', { type: 'geojson', data: this.empty() });

                this.map.addLayer({
                    id: 'parcels-fill', type: 'fill', source: 'parcels',
                    paint: { 'fill-color': '#00b386', 'fill-opacity': 0.4 },
                });
                this.map.addLayer({
                    id: 'parcels-outline', type: 'line', source: 'parcels',
                    paint: { 'line-color': '#39ff14', 'line-width': 1.75 },
                });
                this.map.addLayer({
                    id: 'parcels-labels', type: 'symbol', source: 'parcels',
                    layout: {
                        'text-field': ['to-string', ['get', 'parcel_no']],
                        'text-size': 11,
                        'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    },
                    paint: {
                        'text-color': '#002444',
                        'text-halo-color': '#ffffff',
                        'text-halo-width': 1.5,
                    },
                });

                this.map.on('click', 'parcels-fill', (e) => {
                    const id = e.features[0].properties.id;
                    if (id) window.location.href = '/parcels/' + id;
                });
                this.map.on('mouseenter', 'parcels-fill', () => { this.map.getCanvas().style.cursor = 'pointer'; });
                this.map.on('mouseleave', 'parcels-fill', () => { this.map.getCanvas().style.cursor = ''; });

                this.mapReady = true;
                this.apply();
            });

            // Raised by the owners table when a row's map button is pressed
            window.addEventListener('owner-map-select', (e) => {
                this.selectOwner(String(e.detail.id), e.detail.name ?? '');
            });
        },

        empty() {
            return { type: 'FeatureCollection', features: [] };
        },

        /** owner_ids arrives as a comma-separated string because a parcel may be co-owned. */
        ownsParcel(feature, ownerId) {
            const ids = String(feature.properties.owner_ids ?? '').split(',');

            return ids.includes(ownerId);
        },

        selectOwner(ownerId, ownerName) {
            this.selectedOwnerId = ownerId;
            this.selectedOwnerName = ownerName;
            this.apply();
            document.getElementById('owners-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        showAll() {
            this.selectedOwnerId = null;
            this.selectedOwnerName = '';
            this.apply();
        },

        /** Current features for the selected owner (or all of them). */
        currentFeatures() {
            return this.selectedOwnerId === null
                ? this.allFeatures
                : this.allFeatures.filter((f) => this.ownsParcel(f, this.selectedOwnerId));
        },

        /** Recomputes the count immediately; redraws once the map is ready. */
        apply() {
            const features = this.currentFeatures();
            this.visibleCount = features.length;

            if (! this.mapReady || ! this.map?.getSource('parcels')) return;

            this.render(features);
        },

        render(features) {
            this.map.getSource('parcels').setData({ type: 'FeatureCollection', features });

            if (! features.length) return;

            const bounds = new mapboxgl.LngLatBounds();
            features.forEach((f) => {
                const coords = f.geometry?.coordinates;
                if (! coords) return;
                (f.geometry.type === 'Polygon' ? coords[0] : coords.flat(2)).forEach((c) => bounds.extend(c));
            });
            if (! bounds.isEmpty()) {
                this.map.fitBounds(bounds, { padding: 60, maxZoom: 16 });
            }
        },
    };
}
</script>
@endpush
