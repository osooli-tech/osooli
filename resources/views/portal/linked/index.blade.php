@extends('portal.layout')

@section('title', __('portal.linked_title'))

@section('content')
@php
    $totalParcels = array_sum(array_column($groups, 'parcels_count'));
    $totalArea = array_sum(array_column($groups, 'area'));
    $palette = \App\Services\Portal\AccountOverviewService::PALETTE;
@endphp
<div class="space-y-5">
    <x-portal.page-hero icon="family_restroom" :title="__('portal.linked_title')" :subtitle="__('portal.linked_subtitle')" id="linked">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-6">
            <x-portal.hero-stat icon="group" :label="__('portal.linked_holders')" :value="count($groups)" />
            <x-portal.hero-stat icon="map" :label="__('dashboard.total_parcels')" :value="$totalParcels" />
            <x-portal.hero-stat icon="square_foot" :label="__('dashboard.total_area')" :value="$totalArea" :suffix="__('dashboard.area_unit_sqm')" />
        </div>
    </x-portal.page-hero>

    {{-- Every portfolio on one map: a chip, or a portfolio's own button, shows just that portfolio --}}
    @if ($shapes !== [])
        <section x-data="linkedMap" @linked-focus.window="show($event.detail)" id="linked-map"
                 class="rounded-3xl overflow-hidden bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm scroll-mt-20">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3">
                <button type="button" @click="show(null)"
                        :class="active === null ? 'bg-primary text-white border-primary' : 'border-outline-variant dark:border-white/10'"
                        class="px-3 py-1.5 rounded-full text-xs font-medium border">{{ __('portal.portfolio_all') }}</button>
                @foreach ($groups as $i => $group)
                    <button type="button" @click="show({{ $i }})"
                            :class="active === {{ $i }} ? 'bg-primary text-white border-primary' : 'border-outline-variant dark:border-white/10'"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border">
                        <span class="w-2.5 h-2.5 rounded-full" style="background: {{ $palette[$i % count($palette)] }}"></span>{{ $group['holder'] }}
                        <span class="data-tabular opacity-70">{{ $group['parcels_count'] }}</span>
                    </button>
                @endforeach
            </div>
            <div x-ref="map" class="h-[420px] w-full bg-surface-container"></div>
        </section>
    @endif

    @forelse ($groups as $group)
        {{-- One holder = one portfolio under the parent owner --}}
        <section class="rounded-3xl overflow-hidden bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 bg-gradient-to-l from-primary/10 to-transparent dark:from-white/5">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-11 h-11 shrink-0 rounded-2xl bg-gradient-to-br from-tertiary-container to-tertiary-fixed-dim text-on-tertiary-container flex items-center justify-center font-bold">
                        {{ mb_substr($group['holder'], 0, 1) }}
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-semibold truncate">{{ __('portal.linked_portfolio_of', ['name' => $group['holder']]) }}</h2>
                        <p class="flex items-center gap-1 text-xs text-on-surface-variant dark:text-on-primary-container">
                            <span class="material-symbols-outlined text-[14px]">info</span>{{ ($group['own'] ?? false) ? __('portal.own_portfolio_hint') : __('portal.linked_hint') }}
                        </p>
                    </div>
                </div>
                <dl class="flex items-center gap-5 text-sm">
                    @if ($shapes !== [])
                        <button type="button" @click="$dispatch('linked-focus', {{ $loop->index }})"
                                class="flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-medium bg-primary/10 text-primary dark:bg-white/10 dark:text-white hover:bg-primary/20">
                            <span class="material-symbols-outlined text-[16px]">location_on</span>{{ __('dashboard.portfolio_show_on_map') }}
                        </button>
                    @endif
                    <div class="text-center">
                        <dd class="font-bold data-tabular">{{ $group['parcels_count'] }}</dd>
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('owners.portfolio_parcels_unit') }}</dt>
                    </div>
                    <div class="text-center">
                        <dd class="font-bold data-tabular">{{ number_format($group['area']) }}</dd>
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.area_unit_sqm') }}</dt>
                    </div>
                    @if ($showValue)
                        <div class="text-center">
                            <dd class="font-bold data-tabular text-secondary">{{ number_format((float) $group['value']) }}</dd>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.currency') }}</dt>
                        </div>
                    @endif
                </dl>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 p-4">
                @foreach ($group['parcels'] as $row)
                    <a href="{{ ($group['own'] ?? false) ? route('portal.parcels.show', $row['id']) : route('portal.linked.show', $row['id']) }}"
                       class="flex flex-col h-full rounded-2xl p-4 border border-outline-variant dark:border-white/10 hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-lg font-bold data-tabular">{{ $row['parcel_no'] ?? '—' }}</span>
                            @if ($row['asset_type'])
                                <span class="text-[11px] px-2 py-0.5 rounded-full bg-surface-container dark:bg-white/5">{{ $row['asset_type'] }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-1">
                            {{ __('parcels.plan_no') }} {{ $row['plan_no'] ?? '—' }}@if ($row['district']) · {{ $row['district'] }}@endif
                        </p>
                        <div class="flex items-center justify-between gap-2 mt-auto pt-3 text-sm">
                            <span class="data-tabular">{{ $row['area'] > 0 ? number_format($row['area']).' '.__('dashboard.area_unit_sqm') : '—' }}</span>
                            @if ($showValue && $row['value'] !== null)
                                <span class="font-semibold text-secondary data-tabular">{{ number_format($row['value']) }} {{ __('parcels.sar') }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-3xl p-10 text-center bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10">
            <span class="material-symbols-outlined text-[48px] opacity-30">family_restroom</span>
            <p class="mt-2 text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('portal.linked_empty') }}</p>
        </div>
    @endforelse
</div>
@endsection

@if ($shapes !== [])
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    const shapes = @js($shapes);
    const palette = @js($palette);
    const urls = @js(collect($groups)->map(fn ($g) => ($g['own'] ?? false) ? route('portal.parcels.show', '__ID__') : route('portal.linked.show', '__ID__'))->all());

    Alpine.data('linkedMap', () => ({
        active: null,
        map: null,
        init() {
            const token = @js(config('services.mapbox.token'));
            if (! token || ! window.loadMapbox) return;
            window.loadMapbox().then((mapboxgl) => this.draw(mapboxgl, token))
                .catch(() => console.warn('[Sakuki] mapbox-gl failed to load; the portfolios map is unavailable.'));
        },
        features() {
            return {
                type: 'FeatureCollection',
                features: shapes
                    .filter((s) => this.active === null || s.groups.includes(this.active))
                    .map((s) => {
                        const g = this.active ?? s.groups[0];
                        return { type: 'Feature', geometry: s.geometry, properties: { id: s.id, no: String(s.parcel_no ?? ''), color: palette[g % palette.length], url: urls[g].replace('__ID__', s.id) } };
                    }),
            };
        },
        draw(mapboxgl, token) {
            this.mapboxgl = mapboxgl;
            mapboxgl.accessToken = token;
            const street = () => document.documentElement.classList.contains('dark') ? 'mapbox://styles/mapbox/dark-v11' : 'mapbox://styles/mapbox/light-v11';
            const map = new mapboxgl.Map({ container: this.$refs.map, style: street(), center: [46.7, 24.7], zoom: 9, attributionControl: false });
            this.map = map;
            map.addControl(new mapboxgl.NavigationControl(), 'bottom-left');
            map.addControl(window.sakukiBasemapToggle({
                street,
                labels: { streets: @js(__('parcels.show_streets')), satellite: @js(__('parcels.show_satellite')) },
            }), document.documentElement.dir === 'rtl' ? 'top-right' : 'top-left');
            let framed = false;
            // Every style load: the first and each basemap switch, which wipes layers.
            map.on('style.load', () => {
                map.addSource('lk', { type: 'geojson', data: this.features() });
                map.addLayer({ id: 'lk-fill', type: 'fill', source: 'lk', paint: { 'fill-color': ['get', 'color'], 'fill-opacity': 0.55 } });
                map.addLayer({ id: 'lk-edge', type: 'line', source: 'lk', paint: { 'line-color': '#ffffff', 'line-width': 1.6 } });
                map.addLayer({ id: 'lk-label', type: 'symbol', source: 'lk', minzoom: 14, layout: { 'text-field': ['get', 'no'], 'text-size': 12 }, paint: { 'text-color': '#ffffff', 'text-halo-color': '#0b1c30', 'text-halo-width': 2 } });
                if (framed) return;
                framed = true;
                this.fit(0);
                map.on('click', 'lk-fill', (e) => { window.location.href = e.features[0].properties.url; });
                map.on('mouseenter', 'lk-fill', () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', 'lk-fill', () => { map.getCanvas().style.cursor = ''; });
            });
        },
        show(index) {
            this.active = index;
            this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (! this.map || ! this.map.getSource('lk')) return;
            this.map.getSource('lk').setData(this.features());
            this.fit();
        },
        fit(duration = 800) {
            const bounds = new this.mapboxgl.LngLatBounds();
            this.features().features.forEach((f) => {
                const c = f.geometry.coordinates;
                (f.geometry.type === 'Polygon' ? c[0] : c.flat(2)).forEach((q) => bounds.extend(q));
            });
            if (! bounds.isEmpty()) this.map.fitBounds(bounds, { padding: 50, maxZoom: 17, duration });
        },
    }));
});
</script>
@endpush
@endif
