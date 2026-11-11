@extends('portal.layout')

@section('title', __('portal.overview_title'))

@section('content')
@php
    $totals = $overview['totals'];
    $fmtShare = fn (float $share): string => rtrim(rtrim(number_format($share * 100, 2), '0'), '.').'%';
    // One colour per owner, the same on the cards and the map.
    $palette = ['#1f6f8b', '#c9a84c', '#5b8c5a', '#a05195', '#d45087', '#2f4b7c', '#f95d6a', '#665191'];
@endphp
<div class="space-y-5">
    <x-portal.page-hero :icon="$account->isCompany() ? 'domain' : 'dashboard'"
                        :title="$account->isCompany() && $account->name ? $account->name : __('portal.overview_title')"
                        :subtitle="$account->isCompany() ? __('portal.overview_company_subtitle') : __('portal.overview_subtitle')" id="overview">
        @if ($account->isCompany() && $account->cr_number)
            <p class="mt-2 text-sm font-medium opacity-90">{{ __('portal.cr_number') }}: <span class="data-tabular" dir="ltr">{{ $account->cr_number }}</span></p>
        @elseif (! $account->isCompany() && $account->name)
            <p class="mt-2 text-sm font-medium opacity-90">{{ $account->name }}</p>
        @endif
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
            <x-portal.hero-stat icon="group" :label="__('portal.overview_owners')" :value="count($overview['owners'])" />
            <x-portal.hero-stat icon="map" :label="__('dashboard.total_parcels')" :value="$totals['parcels']" />
            <x-portal.hero-stat icon="square_foot" :label="__('dashboard.total_area')" :value="round($totals['area'])" :suffix="__('dashboard.area_unit_sqm')" />
            @if ($showValue)
                <x-portal.hero-stat icon="payments" :label="__('portal.overview_value')" :value="round((float) $totals['value'])" :suffix="__('parcels.currency')" />
            @endif
        </div>
        <p class="mt-3 flex items-center gap-1 text-xs opacity-80">
            <span class="material-symbols-outlined text-[14px]">info</span>{{ __('portal.overview_total_note') }}
        </p>
    </x-portal.page-hero>

    {{-- Every parcel of the account on one map, coloured by owner --}}
    @if (config('services.mapbox.token'))
        <section class="rounded-3xl overflow-hidden bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                <h2 class="font-semibold">{{ __('portal.overview_map') }}</h2>
                <ul class="flex flex-wrap items-center gap-3 text-xs">
                    @foreach ($overview['owners'] as $i => $card)
                        <li class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full" style="background: {{ $palette[$i % count($palette)] }}"></span>{{ $card['name'] }}
                        </li>
                    @endforeach
                </ul>
            </header>
            <div id="overview-map" class="w-full h-[380px]"
                 data-token="{{ config('services.mapbox.token') }}"
                 data-url="{{ route('portal.overview.map') }}"
                 data-palette="{{ json_encode($palette) }}"></div>
        </section>
    @endif

    @foreach ($overview['owners'] as $i => $card)
        {{-- One owner = one portfolio --}}
        <section class="rounded-3xl overflow-hidden bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 bg-gradient-to-l from-primary/10 to-transparent dark:from-white/5">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-11 h-11 shrink-0 rounded-2xl text-white flex items-center justify-center font-bold" style="background: {{ $palette[$i % count($palette)] }}">
                        {{ mb_substr($card['name'], 0, 1) }}
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-semibold truncate">{{ __('portal.overview_owner_parcels', ['name' => $card['name']]) }}</h2>
                        @unless ($card['can_manage'])
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.view_only') }}</p>
                        @endunless
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <dl class="flex items-center gap-5 text-sm">
                        <div class="text-center">
                            <dd class="font-bold data-tabular">{{ $card['parcels'] }}</dd>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('owners.portfolio_parcels_unit') }}</dt>
                        </div>
                        <div class="text-center">
                            <dd class="font-bold data-tabular">{{ number_format($card['area']) }}</dd>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.area_unit_sqm') }}</dt>
                        </div>
                        @if ($showValue)
                            <div class="text-center">
                                <dd class="font-bold data-tabular text-secondary">{{ number_format((float) $card['value']) }}</dd>
                                <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.currency') }}</dt>
                            </div>
                        @endif
                    </dl>
                    <form method="POST" action="{{ route('portal.switch', $card['owner_id']) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1 rounded-xl px-3 py-2 text-sm font-medium bg-primary text-on-primary hover:opacity-90 transition-opacity">
                            {{ __('portal.overview_open') }}<span class="material-symbols-outlined text-[18px] rtl:rotate-180">arrow_forward</span>
                        </button>
                    </form>
                </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 p-4">
                @foreach ($card['items'] as $item)
                    <form method="POST" action="{{ route('portal.switch', $card['owner_id']) }}">
                        @csrf
                        <input type="hidden" name="parcel" value="{{ $item['id'] }}">
                        <button type="submit" class="w-full text-start flex flex-col h-full rounded-2xl p-4 border border-outline-variant dark:border-white/10 hover:shadow-md hover:-translate-y-0.5 transition-all">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-lg font-bold data-tabular">{{ $item['parcel_no'] ?? '—' }}</span>
                                @if ($item['share'] < 0.9999)
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-tertiary-container/40 data-tabular" dir="ltr">{{ $fmtShare($item['share']) }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container truncate">
                                {{ collect([$item['district'], $item['city']])->filter()->implode('، ') }}@if ($item['plan_no']) · {{ __('parcels.plan_no') }} {{ $item['plan_no'] }}@endif
                            </p>
                            <dl class="mt-3 flex items-center gap-4 text-sm">
                                <div><dd class="font-semibold data-tabular">{{ number_format($item['area']) }}</dd><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.area_unit_sqm') }}</dt></div>
                                @if ($showValue && $item['value'] !== null)
                                    <div><dd class="font-semibold data-tabular text-secondary">{{ number_format($item['value']) }}</dd><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.currency') }}</dt></div>
                                @endif
                            </dl>
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

@if (config('services.mapbox.token'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('overview-map');
                if (! el || ! window.loadMapbox) return;
                const palette = JSON.parse(el.dataset.palette);

                window.loadMapbox().then((mapboxgl) => {
                    mapboxgl.accessToken = el.dataset.token;
                    const dark = document.documentElement.classList.contains('dark');
                    const map = new mapboxgl.Map({
                        container: el,
                        style: dark ? 'mapbox://styles/mapbox/dark-v11' : 'mapbox://styles/mapbox/light-v11',
                        center: [46.7, 24.7],
                        zoom: 9,
                    });

                    map.on('load', async () => {
                        const data = await fetch(el.dataset.url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
                        const colour = ['to-color', ['at', ['%', ['get', 'color_index'], palette.length], ['literal', palette]]];

                        map.addSource('account-parcels', { type: 'geojson', data });
                        map.addLayer({ id: 'account-fill', type: 'fill', source: 'account-parcels', paint: { 'fill-color': colour, 'fill-opacity': 0.45 } });
                        map.addLayer({ id: 'account-line', type: 'line', source: 'account-parcels', paint: { 'line-color': colour, 'line-width': 2 } });

                        const bounds = new mapboxgl.LngLatBounds();
                        data.features.forEach((f) => (f.geometry.type === 'MultiPolygon' ? f.geometry.coordinates.flat(2) : f.geometry.coordinates.flat(1)).forEach((c) => bounds.extend(c)));
                        if (! bounds.isEmpty()) map.fitBounds(bounds, { padding: 40, maxZoom: 16, duration: 0 });

                        map.on('click', 'account-fill', (e) => {
                            const p = e.features[0].properties;
                            const names = JSON.parse(p.owner_names);
                            const box = document.createElement('div');
                            box.className = 'text-sm';
                            const title = document.createElement('strong');
                            title.textContent = p.parcel_no ?? '';
                            box.append(title, document.createElement('br'), document.createTextNode(names.join('، ')));
                            new mapboxgl.Popup().setLngLat(e.lngLat).setDOMContent(box).addTo(map);
                        });
                        map.on('mouseenter', 'account-fill', () => { map.getCanvas().style.cursor = 'pointer'; });
                        map.on('mouseleave', 'account-fill', () => { map.getCanvas().style.cursor = ''; });
                    });
                }).catch(() => {});
            });
        </script>
    @endpush
@endif
@endsection
