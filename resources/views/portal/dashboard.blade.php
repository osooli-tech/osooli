@extends('portal.layout')

@section('title', __('portal.dashboard_title'))

@section('content')
@php
    $font = app()->isLocale('ar') ? 'IBM Plex Sans Arabic' : 'IBM Plex Sans';
    $fmtArea = function (float $v): string {
        if ($v <= 0.0) return '—';
        return $v >= 1_000_000
            ? number_format($v / 1_000_000, 2).' '.__('dashboard.area_unit_km')
            : number_format($v, 0).' '.__('dashboard.area_unit_sqm');
    };
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm border border-outline-variant dark:border-white/10';
    $emptyCls = 'flex items-center justify-center h-44 text-on-surface-variant dark:text-on-primary-container text-sm';
@endphp
@php
    $pf = $insights['portfolio'];
    $health = $insights['health'];
    $fmtMoney = function (?float $v): array {
        if ($v === null) return ['—', ''];
        if ($v >= 1_000_000_000) return [number_format($v / 1_000_000_000, 2), __('portal.unit_billion')];
        if ($v >= 1_000_000) return [number_format($v / 1_000_000, 1), __('portal.unit_million')];
        return [number_format($v, 0), ''];
    };
    [$valueNum, $valueUnit] = $fmtMoney($pf['value']);
    $ring = 2 * M_PI * 42;
    $healthTone = $health['percent'] >= 70 ? 'text-secondary-fixed' : ($health['percent'] >= 40 ? 'text-tertiary-container' : 'text-error-container');
@endphp
<div class="space-y-6">

    {{-- ══ Bento row 1: the 3D map beside the portfolio's value and health ══ --}}
    <div class="grid grid-cols-12 gap-5">

        {{-- The map tile. The selected-parcel drawer floats inside it. --}}
        <div class="col-span-12 xl:col-span-8 relative rounded-3xl overflow-hidden shadow-lg ring-1 ring-outline-variant dark:ring-white/10"
             style="height: 660px;"
             x-data="{
                parcel: null,
                documents: [],
                images: [],
                loading: false,
                fetchDocuments(id) {
                    this.loading = true;
                    this.documents = [];
                    this.images = [];
                    fetch(`{{ url('/portal/parcels') }}/${id}/documents`, { headers: { Accept: 'application/json' } })
                        .then((r) => r.json())
                        .then((data) => { this.documents = data.documents ?? []; this.images = data.images ?? []; })
                        .finally(() => { this.loading = false; });
                },
                deedDocument() { return this.documents.find((d) => d.type === 'صك') ?? null; },
                otherDocuments() { return this.documents.filter((d) => d.type !== 'صك'); },
             }"
             @parcel-selected.window="parcel = $event.detail; fetchDocuments(parcel.id)">

            <div id="sakuki-map"
                 class="w-full h-full bg-surface-container-lowest dark:bg-[#1a1f2e]"
                 data-token="{{ config('services.mapbox.token') }}"
                 data-geojson-url="{{ route('portal.geo.parcels') }}"
                 data-boundaries-url="{{ route('portal.geo.boundaries', '__LEVEL__') }}"
                 data-colors="{{ json_encode($mapColors) }}"
                 data-massing="{{ json_encode($massing) }}"
                 data-can-edit-colors="0">@if (! config('services.mapbox.token'))<div class="flex flex-col items-center justify-center h-full gap-3 text-on-surface-variant dark:text-on-primary-container"><span class="material-symbols-outlined text-[48px] opacity-40">map</span><p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p></div>@endif</div>

            @php
                $floatCls = 'bg-white/75 dark:bg-[#0d1420]/70 backdrop-blur-xl border border-white/40 dark:border-white/10 shadow-lg';
                $rowCls = 'flex items-center gap-2 px-1.5 py-1 rounded-lg cursor-pointer text-xs text-on-surface dark:text-white hover:bg-surface-container dark:hover:bg-white/5';
                $headCls = 'text-[10px] font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-1.5';
            @endphp

            {{-- Search + city filter (options filled by map.js from the owner's own parcels) --}}
            <div class="absolute top-3 start-3 z-10 flex flex-wrap items-center gap-2 max-w-md">
                <div class="relative flex-1 min-w-[170px]">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px] text-on-surface-variant pointer-events-none">search</span>
                    <input id="map-search" type="text" placeholder="{{ __('dashboard.search_map_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2.5 text-sm rounded-xl {{ $floatCls }} text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary/40" />
                </div>
                <select id="map-city-filter"
                        class="px-3 py-2.5 text-sm rounded-xl {{ $floatCls }} text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('dashboard.city_filter_all') }}</option>
                </select>
                <button id="map-filter-clear-btn" type="button" title="{{ __('dashboard.city_filter_clear') }}"
                        class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0 {{ $floatCls }}
                               text-on-surface-variant dark:text-on-primary-container hover:text-error transition-colors">
                    <span class="material-symbols-outlined text-[18px]">filter_alt_off</span>
                </button>
            </div>

            {{-- Layers: colouring, legend, visible layers, boundaries, basemap --}}
            <div class="absolute top-3 end-3 z-10" x-data="{ open: false }">
                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium {{ $floatCls }} text-on-surface dark:text-white">
                    <span class="material-symbols-outlined text-[16px]">layers</span>
                    {{ __('dashboard.map_layers') }}
                </button>

                <div x-show="open" x-cloak @click.outside="open = false"
                     class="mt-2 w-60 max-h-[70vh] overflow-y-auto rounded-xl p-3 space-y-4 {{ $floatCls }}">
                    <div>
                        <p class="{{ $headCls }}">{{ __('dashboard.colour_by') }}</p>
                        <div class="space-y-0.5">
                            @foreach ([
                                'none' => 'dashboard.colour_none',
                                'deed_status' => 'parcels.deed_status',
                                'asset_type' => 'parcels.asset_type',
                                'fall_in' => 'parcels.ownership_basis',
                                'priced' => 'dashboard.colour_priced',
                            ] as $mode => $label)
                                <label class="{{ $rowCls }}">
                                    <input type="radio" name="parcel-colour" value="{{ $mode }}" @checked($mode === 'none') class="accent-secondary w-3.5 h-3.5 shrink-0">
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div id="map-legend" class="hidden">
                        <p class="{{ $headCls }}">{{ __('dashboard.legend') }}</p>
                        <div id="map-legend-items" class="space-y-1"></div>
                    </div>

                    <div>
                        <p class="{{ $headCls }}">{{ __('dashboard.visible_layers') }}</p>
                        <div class="space-y-0.5">
                            @foreach ([
                                'parcels-fill' => ['dashboard.layer_parcels', 'category'],
                                'parcels-outline' => ['dashboard.layer_outlines', 'pentagon'],
                                'parcels-labels' => ['dashboard.show_labels', 'label'],
                            ] as $layer => [$label, $icon])
                                <label class="{{ $rowCls }}">
                                    <input type="checkbox" checked data-layer="{{ $layer }}" class="accent-secondary w-3.5 h-3.5 shrink-0">
                                    <span class="material-symbols-outlined text-[15px]">{{ $icon }}</span>
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="{{ $headCls }}">{{ __('dashboard.boundaries') }}</p>
                        <div class="space-y-0.5">
                            @foreach ([
                                'regions' => ['dashboard.boundary_regions', 'public'],
                                'cities' => ['dashboard.boundary_cities', 'location_city'],
                                'districts' => ['dashboard.boundary_districts', 'holiday_village'],
                            ] as $level => [$label, $icon])
                                <label class="{{ $rowCls }}">
                                    <input type="checkbox" data-boundary="{{ $level }}" class="accent-secondary w-3.5 h-3.5 shrink-0">
                                    <span class="material-symbols-outlined text-[15px]">{{ $icon }}</span>
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-1 border-t border-outline-variant dark:border-white/10">
                        <button id="toggle-basemap" type="button"
                                class="flex items-center gap-2 w-full px-1.5 py-1.5 rounded-lg text-xs font-medium text-on-surface dark:text-white hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <span class="material-symbols-outlined text-[16px]">satellite_alt</span>
                            <span id="basemap-label"
                                  data-satellite-label="{{ __('dashboard.satellite_view') }}"
                                  data-street-label="{{ __('dashboard.street_view') }}">{{ __('dashboard.satellite_view') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- 3D switch, beside the layers button --}}
            <button id="map-3d-toggle" type="button" aria-pressed="true"
                    data-on="{{ __('portal.map_3d_on') }}" data-off="{{ __('portal.map_3d_off') }}"
                    class="absolute top-3 end-36 z-10 flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold
                           bg-gradient-to-l from-tertiary-container to-tertiary-fixed-dim text-on-tertiary-container shadow-lg">
                <span class="material-symbols-outlined text-[16px]">view_in_ar</span>
                <span data-label>{{ __('portal.map_3d_on') }}</span>
            </button>

            {{-- What the 3D blocks mean: by property type (default) or by price --}}
            <div id="map-3d-mode" role="group"
                 class="absolute top-14 end-3 z-10 flex p-1 rounded-xl text-[11px] font-bold bg-white/75 dark:bg-[#0d1420]/70 backdrop-blur-xl border border-white/40 dark:border-white/10 shadow-lg">
                @foreach (['type' => 'category', 'price' => 'payments'] as $mode => $icon)
                    <button type="button" data-mode="{{ $mode }}" aria-pressed="{{ $mode === 'type' ? 'true' : 'false' }}"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg transition-colors text-on-surface-variant dark:text-on-primary-container
                                   aria-pressed:bg-primary aria-pressed:text-white dark:aria-pressed:bg-tertiary-container dark:aria-pressed:text-on-tertiary-container">
                        <span class="material-symbols-outlined text-[15px]">{{ $icon }}</span>{{ __('portal.map_mode_'.$mode) }}
                    </button>
                @endforeach
            </div>

            <div class="absolute bottom-10 end-3 z-10 w-60 rounded-2xl p-3 bg-white/75 dark:bg-[#0d1420]/70 backdrop-blur-xl border border-white/40 dark:border-white/10 shadow-lg">
                {{-- By type: only the categories this owner actually has are shown --}}
                <div data-legend="type">
                    <p class="text-[11px] font-semibold mb-2 flex items-center gap-1"><span class="material-symbols-outlined text-[15px] text-tertiary">category</span>{{ __('portal.map_type_legend') }}</p>
                    <ul class="grid grid-cols-2 gap-x-2 gap-y-1 text-[10px]">
                        @foreach ($massing as $key => $cat)
                            <li data-cat="{{ $key }}" hidden><span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm shrink-0" style="background: {{ $cat['color'] }}"></span>{{ $cat['label'] }}</span></li>
                        @endforeach
                    </ul>
                </div>
                {{-- By price: a column's height and colour follow the parcel's value --}}
                <div data-legend="price" hidden>
                    <p class="text-[11px] font-semibold mb-2 flex items-center gap-1"><span class="material-symbols-outlined text-[15px] text-tertiary">height</span>{{ __('portal.map_height_legend') }}</p>
                    <div class="h-2.5 rounded-full bg-gradient-to-r from-[#e6c364] via-[#68dbae] to-[#abc9f2]"></div>
                    <div class="flex justify-between text-[10px] text-on-surface-variant dark:text-on-primary-container mt-1">
                        <span>{{ __('portal.map_low') }}</span><span>{{ __('portal.map_high') }}</span>
                    </div>
                </div>
            </div>

            {{-- Selected-parcel drawer — read-only: parcel data, deed file, documents and photos --}}
            <div x-show="parcel" x-cloak
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute top-16 bottom-3 start-3 z-20 w-[22rem] max-w-[calc(100%-1.5rem)] flex flex-col rounded-2xl overflow-hidden
                        bg-white/90 dark:bg-[#0d1420]/90 backdrop-blur-xl border border-white/50 dark:border-white/10 shadow-2xl">
                <div class="flex items-center justify-between gap-2 px-4 py-3 bg-gradient-to-l from-primary to-primary-container text-white shrink-0">
                    <h3 class="text-sm font-semibold flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-tertiary-fixed-dim">pin_drop</span>{{ __('dashboard.parcel_details') }}</h3>
                    <button type="button" @click="parcel = null; $dispatch('parcel-cleared')" class="text-white/70 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                <template x-if="parcel">
                    <div class="space-y-6 text-sm">
                        <dl class="space-y-2.5">
                            @foreach ([
                                'parcels.parcel_no' => 'parcel.parcel_no',
                                'parcels.spatial_id' => 'parcel.geo_id',
                                'parcels.asset_type' => 'parcel.asset_type',
                                'parcels.plan_no' => 'parcel.plan_no',
                                'parcels.district' => 'parcel.district_name',
                                'parcels.deed_no' => 'parcel.deed_no',
                                'parcels.deed_date' => 'parcel.deed_date_hijri',
                                'parcels.deed_status' => 'parcel.deed_status',
                                'parcels.deed_class' => 'parcel.deed_class',
                            ] as $label => $expr)
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __($label) }}</dt>
                                    <dd class="font-semibold data-tabular text-end" x-text="{{ $expr }} ?? '—'"></dd>
                                </div>
                            @endforeach
                            <div class="flex justify-between items-start gap-2">
                                <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.area_deed') }}</dt>
                                <dd class="font-semibold data-tabular text-end"
                                    x-text="parcel.deed_area ? Number(parcel.deed_area).toLocaleString() + ' {{ __('dashboard.area_unit_sqm') }}' : '—'"></dd>
                            </div>
                            <div class="flex justify-between items-start gap-2">
                                <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.m_price') }}</dt>
                                <dd class="font-semibold data-tabular text-end"
                                    x-text="parcel.m_price ? Number(parcel.m_price).toLocaleString() + ' {{ __('parcels.sar') }}' : '—'"></dd>
                            </div>
                            {{-- The stored value, else price per m² × deed area — the same rule as the 3D price view --}}
                            <div class="flex justify-between items-center gap-2 rounded-xl px-3 py-2 bg-tertiary-container/25 dark:bg-tertiary-container/15">
                                <dt class="font-semibold shrink-0">{{ __('parcels.parcel_price') }}</dt>
                                <dd class="font-bold data-tabular text-end text-base"
                                    x-text="(() => { const v = parcel.parcel_price ?? (parcel.m_price && parcel.deed_area ? parcel.m_price * Number(parcel.deed_area) : null); return v ? Math.round(v).toLocaleString() + ' {{ __('parcels.sar') }}' : '—'; })()"></dd>
                            </div>
                        </dl>

                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('dashboard.deed_document') }}
                            </p>
                            <p x-show="loading" class="text-xs text-on-surface-variant dark:text-on-primary-container">…</p>
                            <p x-show="! loading && ! deedDocument()" class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.deed_not_available') }}</p>
                            <div class="flex items-center gap-2" x-show="! loading && deedDocument()">
                                <a :href="deedDocument()?.download_url" target="_blank"
                                   class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium bg-primary text-white hover:opacity-90">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>{{ __('dashboard.view_deed') }}
                                </a>
                                <a :href="deedDocument()?.download_url" download
                                   class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium bg-secondary text-white hover:opacity-90">
                                    <span class="material-symbols-outlined text-[15px]">download</span>{{ __('dashboard.download_deed') }}
                                </a>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('dashboard.related_documents') }}
                            </p>
                            <p x-show="loading" class="text-xs text-on-surface-variant dark:text-on-primary-container">…</p>
                            <p x-show="! loading && otherDocuments().length === 0" class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.no_documents') }}</p>
                            <div class="space-y-2" x-show="! loading && otherDocuments().length > 0">
                                <template x-for="doc in otherDocuments()" :key="doc.id">
                                    <div class="flex items-center justify-between gap-2 rounded-xl bg-surface-container dark:bg-white/5 px-2.5 py-2">
                                        <span class="flex items-center gap-1.5 text-xs font-medium min-w-0 truncate">
                                            <span class="material-symbols-outlined text-[15px] shrink-0">description</span>
                                            <span x-text="doc.type ?? '—'"></span>
                                        </span>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <a :href="doc.download_url" target="_blank"
                                               class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-medium bg-primary text-white hover:opacity-90">
                                                <span class="material-symbols-outlined text-[13px]">visibility</span>{{ __('dashboard.view_document') }}
                                            </a>
                                            <a :href="doc.download_url" download
                                               class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-medium bg-secondary text-white hover:opacity-90">
                                                <span class="material-symbols-outlined text-[13px]">download</span>{{ __('dashboard.download_document') }}
                                            </a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-outline-variant dark:border-white/10" x-show="! loading && images.length > 0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('parcels.photos_section') }}
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="img in images" :key="img.id">
                                    <div class="aspect-square rounded-xl overflow-hidden bg-surface-container dark:bg-white/5">
                                        <img :src="img.url" alt="" class="w-full h-full object-cover">
                                    </div>
                                </template>
                            </div>
                        </div>

                        <a :href="parcel.id ? '{{ url('/portal/parcels') }}/' + parcel.id : '#'"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-medium bg-primary text-white hover:opacity-90">
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>{{ __('dashboard.view_full_details') }}
                        </a>
                    </div>
                </template>
                </div>
            </div>
        </div>

        {{-- Value + health column --}}
        <div class="col-span-12 xl:col-span-4 flex flex-col gap-5">
            <section class="relative overflow-hidden rounded-3xl text-white p-6 bg-gradient-to-br from-primary via-primary-container to-on-secondary-fixed-variant shadow-lg">
                <svg class="absolute inset-0 w-full h-full opacity-[0.09] pointer-events-none" aria-hidden="true">
                    <defs><pattern id="plat-value" width="48" height="36" patternUnits="userSpaceOnUse"><path d="M48 0H0V36" fill="none" stroke="white" stroke-width="1" /></pattern></defs>
                    <rect width="100%" height="100%" fill="url(#plat-value)" />
                </svg>
                <div class="absolute -top-20 -end-10 w-64 h-64 rounded-full bg-tertiary-container/30 blur-3xl pointer-events-none"></div>
                <div class="relative">
                    <p class="text-sm text-primary-fixed-dim">{{ __('portal.welcome', ['name' => $greetingName]) }}</p>
                    <p class="text-xs text-primary-fixed-dim/80 mt-0.5">{{ __('portal.hero_value_label') }}</p>
                    @php $valueRaw = (float) str_replace(',', '', $valueNum); $valueDec = str_contains($valueNum, '.') ? strlen(substr(strrchr($valueNum, '.'), 1)) : 0; @endphp
                    <p class="mt-3 flex items-baseline gap-2 flex-wrap">
                        <span class="text-6xl font-bold data-tabular tracking-tight bg-gradient-to-l from-tertiary-fixed to-white bg-clip-text text-transparent"
                              @if ($pf['value'] !== null) x-data="countUp({{ $valueRaw }}, {{ $valueDec }})" x-text="display" @endif>{{ $valueNum }}</span>
                        <span class="text-lg text-tertiary-container font-semibold">{{ $valueUnit }} {{ __('parcels.currency') }}</span>
                    </p>
                    <div class="mt-5 grid grid-cols-2 gap-2">
                        @foreach ([
                            ['icon' => 'map', 'text' => __('portal.hero_parcels', ['count' => number_format($pf['parcels'])])],
                            ['icon' => 'square_foot', 'text' => $fmtArea($pf['area'])],
                            ['icon' => 'holiday_village', 'text' => __('portal.hero_districts', ['count' => $pf['districts']])],
                            ['icon' => 'location_city', 'text' => __('portal.hero_cities', ['count' => $pf['cities']])],
                        ] as $chip)
                            <span class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 ring-1 ring-white/10 text-sm">
                                <span class="material-symbols-outlined text-[17px] text-tertiary-fixed-dim">{{ $chip['icon'] }}</span>{{ $chip['text'] }}
                            </span>
                        @endforeach
                    </div>
                    @if ($pf['football_pitches'] >= 1)
                        <p class="mt-4 flex items-center gap-2 text-sm text-primary-fixed-dim">
                            <span class="material-symbols-outlined text-[18px]">sports_soccer</span>{{ __('portal.hero_pitches', ['count' => number_format($pf['football_pitches'], 1)]) }}
                        </p>
                    @endif
                </div>
            </section>

            {{-- Health: four rings, each a real share of the portfolio --}}
            @php
                $tot = max(1, $health['total']);
                $rings = [
                    ['label' => __('portal.ring_completeness'), 'value' => $health['percent']],
                    ['label' => __('portal.ring_deed_scans'), 'value' => (int) round($health['documented'] / $tot * 100)],
                    ['label' => __('portal.ring_priced'), 'value' => (int) round($health['priced'] / $tot * 100)],
                    ['label' => __('portal.ring_matched'), 'value' => (int) round($health['matched'] / $tot * 100)],
                ];
            @endphp
            <section class="{{ $cardCls }} flex-1">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <span class="material-symbols-outlined text-[18px] text-secondary">health_and_safety</span>{{ __('portal.health_title') }}
                </h2>
                <div wire:ignore x-data="{ init() {
                    themedChart(this.$refs.el, (dark) => ({
                        chart: { type: 'radialBar', height: 300, background: 'transparent', fontFamily: '{{ $font }}', animations: { speed: 1200 } },
                        series: @js(array_column($rings, 'value')),
                        labels: @js(array_column($rings, 'label')),
                        colors: ['#006c4e', '#002444', '#c9a84c', '#68dbae'],
                        theme: { mode: dark ? 'dark' : 'light' },
                        plotOptions: { radialBar: {
                            hollow: { size: '28%' },
                            track: { background: 'rgba(125,140,160,0.12)', margin: 6 },
                            dataLabels: { name: { fontSize: '12px' }, value: { fontSize: '20px', fontWeight: 700, formatter: (v) => Math.round(v) + '%' },
                                total: { show: true, label: @js(__('portal.ring_overall')), formatter: () => '{{ $health['percent'] }}%' } },
                        } },
                        stroke: { lineCap: 'round' },
                        legend: { show: true, position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}', markers: { size: 5 } },
                    }));
                } }"><div x-ref="el"></div></div>
            </section>
        </div>
    </div>

    {{-- ── Needs attention ───────────────────────────────────────────────── --}}
    <section>
        <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
            <span class="material-symbols-outlined text-[18px] text-tertiary">notifications_active</span>
            {{ __('portal.attention_title') }}
        </h2>
        @if (empty($insights['alerts']))
            <div class="flex items-center gap-3 rounded-2xl p-4 bg-secondary/10 text-secondary border border-secondary/20">
                <span class="material-symbols-outlined text-[26px]">verified</span>
                <p class="text-sm font-medium">{{ __('portal.attention_all_clear') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach ($insights['alerts'] as $alert)
                    @php
                        $tone = $alert['tone'] === 'warning'
                            ? 'bg-tertiary-container/25 border-tertiary-container/50 text-on-surface dark:text-white'
                            : 'bg-primary/5 dark:bg-white/5 border-outline-variant dark:border-white/10 text-on-surface dark:text-white';
                        $href = $alert['key'] === 'pending_requests' ? route('portal.modification-requests.index') : route('portal.parcels.index');
                    @endphp
                    <a href="{{ $href }}" class="flex gap-3 rounded-2xl p-4 border {{ $tone }} hover:shadow-sm transition-shadow">
                        <span class="material-symbols-outlined text-[24px] {{ $alert['tone'] === 'warning' ? 'text-tertiary' : 'text-primary dark:text-primary-fixed-dim' }}">{{ $alert['icon'] }}</span>
                        <span class="min-w-0">
                            <span class="block text-2xl font-bold data-tabular leading-none">{{ $alert['count'] }}</span>
                            <span class="block text-sm mt-1">{{ __('portal.alert_'.$alert['key']) }}</span>
                            @if (! empty($alert['parcels']))
                                <span class="block text-xs text-on-surface-variant dark:text-on-primary-container mt-1 truncate data-tabular">
                                    {{ __('parcels.parcel_no') }}: {{ implode('، ', $alert['parcels']) }}
                                </span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
    {{-- ══ KPI wall: figures an owner would not work out alone, each with its own small visual ══ --}}
    @php
        $k = $insights['kpis'];
        $money = function (?float $v): string {
            if ($v === null) return '—';
            if ($v >= 1_000_000) return number_format($v / 1_000_000, 2).' '.__('portal.unit_million');
            return number_format($v);
        };
        $semi = fn (float $pct): string => sprintf('%.2f 126', 126 * max(0, min(100, $pct)) / 100);
        $ringLen = 2 * M_PI * 16;
        $divLabel = $k['diversification'] === null ? null : ($k['diversification'] >= 60 ? 'kpi_div_high' : ($k['diversification'] >= 30 ? 'kpi_div_mid' : 'kpi_div_low'));
        $tileCls = 'group relative overflow-hidden rounded-3xl p-5 bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300';
    @endphp
    <section>
        <div class="flex items-end justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-[22px] text-tertiary">insights</span>{{ __('portal.kpi_wall_title') }}</h2>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_wall_hint') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">

            {{-- 1. Average value per parcel --}}
            <div class="{{ $tileCls }} col-span-2 xl:col-span-1 bg-gradient-to-br from-secondary to-on-secondary-fixed-variant !border-transparent text-white">
                <span class="absolute -bottom-10 -end-10 w-32 h-32 rounded-full bg-white/10"></span>
                <span class="material-symbols-outlined text-[26px] text-secondary-fixed">price_check</span>
                <p class="text-3xl font-bold data-tabular mt-3">{{ $money($k['avg_value']) }}</p>
                <p class="text-sm text-secondary-fixed">{{ __('portal.kpi_avg_value') }} · {{ __('parcels.currency') }}</p>
            </div>

            {{-- 2. Average price per m² --}}
            <div class="{{ $tileCls }}">
                <div class="flex items-center justify-between">
                    <span class="w-10 h-10 rounded-2xl bg-primary/10 dark:bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined text-[22px] text-primary dark:text-primary-fixed-dim">grid_4x4</span></span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-surface-container dark:bg-white/5">{{ __('portal.kpi_per_sqm_unit') }}</span>
                </div>
                <p class="text-2xl font-bold data-tabular mt-4">{{ $k['avg_price_per_sqm'] === null ? '—' : number_format($k['avg_price_per_sqm']) }}</p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_avg_price_per_sqm') }}</p>
            </div>

            {{-- 3. Diversification gauge --}}
            <div class="{{ $tileCls }}">
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-secondary">diversity_3</span>{{ __('portal.kpi_diversification') }}</p>
                <svg viewBox="0 0 100 58" class="w-full max-w-[9rem] mx-auto mt-2" aria-hidden="true">
                    <path d="M10 52 A40 40 0 0 1 90 52" fill="none" stroke-width="10" stroke-linecap="round" class="stroke-surface-container-high dark:stroke-white/10" />
                    <path d="M10 52 A40 40 0 0 1 90 52" fill="none" stroke-width="10" stroke-linecap="round" class="stroke-secondary" stroke-dasharray="{{ $semi((float) ($k['diversification'] ?? 0)) }}" />
                    <text x="50" y="50" text-anchor="middle" font-size="18" font-weight="700" class="fill-on-surface dark:fill-white">{{ $k['diversification'] ?? '—' }}</text>
                </svg>
                <p class="text-center text-xs font-semibold mt-1">{{ $divLabel ? __('portal.'.$divLabel) : '—' }}</p>
            </div>

            {{-- 4. Concentration: largest parcel's share --}}
            <div class="{{ $tileCls }}">
                <div class="flex items-center gap-3">
                    <div class="relative w-16 h-16 shrink-0">
                        <svg viewBox="0 0 40 40" class="w-full h-full -rotate-90" aria-hidden="true">
                            <circle cx="20" cy="20" r="16" fill="none" stroke-width="5" class="stroke-surface-container-high dark:stroke-white/10" />
                            <circle cx="20" cy="20" r="16" fill="none" stroke-width="5" stroke-linecap="round" class="stroke-tertiary-container"
                                    stroke-dasharray="{{ round($ringLen * ($k['top_share'] ?? 0) / 100, 2) }} {{ round($ringLen, 2) }}" />
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-xs font-bold data-tabular">{{ $k['top_share'] !== null ? round($k['top_share']).'%' : '—' }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ __('portal.kpi_top_share') }}</p>
                        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_top_share_hint') }}</p>
                    </div>
                </div>
            </div>

            {{-- 5. Market premium --}}
            @php $prem = $k['market_premium']; $premUp = $prem !== null && $prem >= 0; @endphp
            <div class="{{ $tileCls }}">
                <span class="w-10 h-10 rounded-2xl flex items-center justify-center {{ $prem === null ? 'bg-surface-container dark:bg-white/5' : ($premUp ? 'bg-secondary/10' : 'bg-error/10') }}">
                    <span class="material-symbols-outlined text-[22px] {{ $prem === null ? 'text-outline' : ($premUp ? 'text-secondary' : 'text-error') }}">{{ $premUp ? 'trending_up' : 'trending_down' }}</span>
                </span>
                <p class="text-2xl font-bold data-tabular mt-4 {{ $prem === null ? '' : ($premUp ? 'text-secondary' : 'text-error') }}">
                    {{ $prem === null ? '—' : (abs($prem) < 0.5 ? '±0%' : ($premUp ? '+' : '').$prem.'%') }}
                </p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_market_premium') }}</p>
            </div>

            {{-- 6. Average deed age --}}
            <div class="{{ $tileCls }}">
                <span class="w-10 h-10 rounded-2xl bg-tertiary-container/20 flex items-center justify-center"><span class="material-symbols-outlined text-[22px] text-tertiary">hourglass_bottom</span></span>
                <p class="text-2xl font-bold data-tabular mt-4">{{ $k['avg_deed_age'] !== null ? number_format($k['avg_deed_age'], 1) : '—' }} <span class="text-sm font-medium">{{ __('portal.kpi_years') }}</span></p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_deed_age') }}</p>
            </div>

            {{-- 7. Surveyed vs deed area --}}
            @php $gap = $k['area_gap']; @endphp
            <div class="{{ $tileCls }}">
                <span class="w-10 h-10 rounded-2xl bg-primary/10 dark:bg-white/10 flex items-center justify-center"><span class="material-symbols-outlined text-[22px] text-primary dark:text-primary-fixed-dim">compare_arrows</span></span>
                <p class="text-2xl font-bold data-tabular mt-4" dir="ltr">{{ $gap === null ? '—' : ($gap > 0 ? '+' : '').number_format($gap) }} <span class="text-sm font-medium">{{ __('dashboard.area_unit_sqm') }}</span></p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_area_gap') }}</p>
            </div>

            {{-- 8. Corner lots share --}}
            <div class="{{ $tileCls }}">
                <div class="flex items-center justify-between">
                    <span class="w-10 h-10 rounded-2xl bg-tertiary-container/20 flex items-center justify-center"><span class="material-symbols-outlined text-[22px] text-tertiary">turn_right</span></span>
                    <span class="text-2xl font-bold data-tabular">{{ $k['corner_share'] !== null ? $k['corner_share'].'%' : '—' }}</span>
                </div>
                <div class="mt-4 h-2 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-l from-tertiary-container to-tertiary-fixed" style="width: {{ $k['corner_share'] ?? 0 }}%"></div>
                </div>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-2">{{ __('portal.kpi_corner_share') }}</p>
            </div>

            {{-- 9. Co-owned parcels --}}
            <div class="{{ $tileCls }}">
                <span class="w-10 h-10 rounded-2xl bg-secondary/10 flex items-center justify-center"><span class="material-symbols-outlined text-[22px] text-secondary">group</span></span>
                <p class="text-2xl font-bold data-tabular mt-4">{{ $k['co_owned'] }} <span class="text-sm font-medium text-on-surface-variant dark:text-on-primary-container">/ {{ $pf['parcels'] }}</span></p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.kpi_co_owned') }}</p>
            </div>

            {{-- 10. Deeds · plans · avg m² price, kept from the earlier strip --}}
            <div class="{{ $tileCls }} col-span-2 lg:col-span-1">
                <div class="space-y-2.5 text-sm">
                    @foreach ([
                        ['icon' => 'description', 'label' => __('dashboard.total_deeds'), 'value' => number_format($summary['deeds_active'] + $summary['deeds_expired'])],
                        ['icon' => 'grid_view', 'label' => __('dashboard.total_plans'), 'value' => number_format($plansCount)],
                        ['icon' => 'payments', 'label' => __('dashboard.avg_price_per_metre'), 'value' => $portfolio['avg_m_price'] !== null ? number_format($portfolio['avg_m_price']).' '.__('parcels.sar') : '—'],
                    ] as $mini)
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 text-on-surface-variant dark:text-on-primary-container"><span class="material-symbols-outlined text-[17px]">{{ $mini['icon'] }}</span>{{ $mini['label'] }}</span>
                            <span class="font-bold data-tabular">{{ $mini['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══ Bento row 2: where the value sits · ownership story · file health ══ --}}
    <div class="grid grid-cols-12 gap-5">
        <section class="col-span-12 lg:col-span-6 {{ $cardCls }}">
            <h2 class="flex items-center gap-2 text-sm font-semibold mb-1">
                <span class="material-symbols-outlined text-[18px] text-secondary">dashboard</span>{{ __('portal.value_by_district_title') }}
            </h2>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.treemap_hint') }}</p>
            @if (empty($insights['byDistrict']))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore x-data="{ init() {
                    themedChart(this.$refs.el, (dark) => ({
                        chart: { type: 'treemap', height: 300, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                        series: [{ data: @js(array_map(fn ($r) => ['x' => $r['name'], 'y' => round($r['value'])], $insights['byDistrict'])) }],
                        colors: ['#002444', '#006c4e', '#c9a84c', '#1b3a5c', '#68dbae', '#755b00', '#436084'],
                        theme: { mode: dark ? 'dark' : 'light' },
                        plotOptions: { treemap: { distributed: true, enableShades: false, borderRadius: 10 } },
                        dataLabels: { enabled: true, style: { fontSize: '14px', fontWeight: 700 },
                            formatter: (text, op) => [text, (op.value / 1e6).toFixed(1) + ' ' + @js(__('portal.unit_million'))] },
                        tooltip: { y: { formatter: (v) => Number(v).toLocaleString() + ' ' + @js(__('parcels.currency')) } },
                        legend: { show: false },
                    }));
                } }"><div x-ref="el"></div></div>
            @endif
        </section>

        <section class="col-span-12 md:col-span-6 lg:col-span-3 {{ $cardCls }}">
            <h2 class="flex items-center gap-2 text-sm font-semibold mb-1">
                <span class="material-symbols-outlined text-[18px] text-tertiary">timeline</span>{{ __('portal.timeline_title') }}
            </h2>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.timeline_hint') }}</p>
            @if (empty($insights['timeline']))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore x-data="{ init() {
                    themedChart(this.$refs.el, (dark) => ({
                        chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                        series: [{ name: @js(__('dashboard.total_parcels')), data: @js(array_values($insights['timeline'])) }],
                        xaxis: { categories: @js(array_map(fn ($y) => $y.__('portal.hijri_suffix'), array_keys($insights['timeline']))) },
                        yaxis: { labels: { formatter: (v) => Math.round(v) } },
                        colors: ['#c9a84c'],
                        theme: { mode: dark ? 'dark' : 'light' },
                        plotOptions: { bar: { borderRadius: 8, columnWidth: '42%' } },
                        fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', gradientToColors: ['#002444'], opacityFrom: 1, opacityTo: 0.9 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'rgba(125,140,160,0.15)', strokeDashArray: 4 },
                    }));
                } }"><div x-ref="el"></div></div>
            @endif
        </section>

        <section class="col-span-12 md:col-span-6 lg:col-span-3 {{ $cardCls }}">
            <h2 class="flex items-center gap-2 text-sm font-semibold mb-4">
                <span class="material-symbols-outlined text-[18px] text-primary dark:text-primary-fixed-dim">folder_managed</span>{{ __('portal.file_health_title') }}
            </h2>
            @php $docPct = $health['total'] > 0 ? round($health['documented'] / $health['total'] * 100) : 0; @endphp
            <div class="flex items-center justify-between text-sm">
                <span>{{ __('portal.deed_scans_on_file') }}</span>
                <span class="font-semibold data-tabular">{{ $health['documented'] }} / {{ $health['total'] }}</span>
            </div>
            <div class="mt-1.5 h-2.5 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-l from-primary to-surface-tint" style="width: {{ $docPct }}%"></div>
            </div>
            @if (! empty($health['top_missing']))
                <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container mt-5 mb-2">{{ __('portal.most_missing_fields') }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($health['top_missing'] as $field => $count)
                        <span class="inline-flex items-center gap-1 text-xs rounded-full px-2.5 py-1 bg-tertiary-container/20 text-on-surface dark:text-white">
                            {{ __('parcels.'.$field) }} <span class="data-tabular opacity-70">· {{ $count }}</span>
                        </span>
                    @endforeach
                </div>
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-3">{{ __('portal.most_missing_hint') }}</p>
            @endif
        </section>
    </div>


    {{-- ══ Bento row 3: price vs market · most valuable parcels ══ --}}
    <div class="grid grid-cols-12 gap-5 items-start">
        <div class="col-span-12 xl:col-span-7">
    {{-- ── Your price per m² against the district market ────────────────── --}}
    @if (! empty($insights['priceComparison']))
        <section class="{{ $cardCls }}">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <span class="material-symbols-outlined text-[18px] text-secondary">query_stats</span>
                        {{ __('portal.price_compare_title') }}
                    </h2>
                    <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-1">{{ __('portal.price_compare_hint') }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-gradient-to-l from-secondary to-secondary-fixed-dim"></span>{{ __('portal.price_mine') }}</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-outline-variant dark:bg-white/30"></span>{{ __('portal.price_market') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                @foreach ($insights['priceComparison'] as $cmp)
                    @php
                        $scale = max($cmp['mine'], $cmp['market'] ?? 0) ?: 1;
                        $up = $cmp['diff'] !== null && $cmp['diff'] >= 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="font-semibold text-sm">{{ $cmp['district'] }}</span>
                            @if ($cmp['diff'] !== null)
                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-xs font-bold data-tabular
                                             {{ $up ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error' }}">
                                    <span class="material-symbols-outlined text-[15px]">{{ $up ? 'trending_up' : 'trending_down' }}</span>
                                    {{ abs($cmp['diff']) < 0.5 ? '=' : (($up ? '+' : '').$cmp['diff'].'%') }}
                                </span>
                            @endif
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-3 rounded-full bg-surface-container dark:bg-white/5 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-l from-secondary to-secondary-fixed-dim" style="width: {{ round($cmp['mine'] / $scale * 100) }}%"></div>
                                </div>
                                <span class="w-24 text-xs font-bold data-tabular text-end">{{ number_format($cmp['mine']) }} {{ __('parcels.sar') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-3 rounded-full bg-surface-container dark:bg-white/5 overflow-hidden">
                                    @if ($cmp['market'] !== null)
                                        <div class="h-full rounded-full bg-outline-variant dark:bg-white/30" style="width: {{ round($cmp['market'] / $scale * 100) }}%"></div>
                                    @endif
                                </div>
                                <span class="w-24 text-xs data-tabular text-end text-on-surface-variant dark:text-on-primary-container">
                                    {{ $cmp['market'] !== null ? number_format($cmp['market']).' '.__('parcels.sar') : '—' }}
                                </span>
                            </div>
                        </div>
                        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1.5">
                            {{ $cmp['market'] !== null
                                ? __('portal.price_compare_basis', ['count' => $cmp['comparables']])
                                : __('portal.price_compare_too_few') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
        </div>
        <div class="col-span-12 xl:col-span-5">
    {{-- ── Most valuable parcels, each drawn from its real outline ─────────── --}}
    @if (! empty($insights['topParcels']))
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <span class="material-symbols-outlined text-[18px] text-tertiary">workspace_premium</span>
                    {{ __('portal.top_parcels_title') }}
                </h2>
                <a href="{{ route('portal.parcels.index') }}" class="text-xs text-primary dark:text-primary-fixed-dim hover:underline">{{ __('portal.profile_view_all') }}</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach ($insights['topParcels'] as $i => $p)
                    @php [$pNum, $pUnit] = $fmtMoney($p['value']); @endphp
                    <a href="{{ route('portal.parcels.show', $p['id']) }}"
                       class="group relative {{ $cardCls }} !p-4 flex flex-col hover:border-secondary/50 transition-colors">
                        <span class="absolute top-3 end-3 text-[11px] font-bold w-6 h-6 rounded-full flex items-center justify-center
                                     {{ $i === 0 ? 'bg-tertiary-container text-on-tertiary-container' : 'bg-surface-container dark:bg-white/10' }}">{{ $i + 1 }}</span>
                        <x-parcel-shape :geojson="$p['geom_json']" class="w-20 h-20 mx-auto my-2 transition-transform group-hover:scale-105" />
                        <p class="text-sm font-semibold data-tabular">{{ __('parcels.parcel_no') }} {{ $p['parcel_no'] }}</p>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container truncate">{{ $p['district'] ?? '—' }}</p>
                        <p class="mt-auto pt-2 text-base font-bold text-secondary data-tabular">
                            {{ $pNum }} <span class="text-[11px] font-normal text-on-surface-variant dark:text-on-primary-container">{{ $pUnit }} {{ __('parcels.currency') }}</span>
                        </p>
                        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container data-tabular">{{ number_format($p['area'] ?? 0) }} {{ __('dashboard.area_unit_sqm') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
        </div>
    </div>

    {{-- ── How your parcels sit on the street ──────────────────────────── --}}
    @php $fr = $insights['frontage']; @endphp
    @if ($fr['surveyed'] > 0)
        <section class="{{ $cardCls }}">
            <h2 class="flex items-center gap-2 text-sm font-semibold mb-1">
                <span class="material-symbols-outlined text-[18px] text-tertiary">signpost</span>
                {{ __('portal.frontage_profile_title') }}
            </h2>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-5">{{ __('portal.frontage_profile_hint', ['count' => $fr['surveyed']]) }}</p>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                {{-- Compass: how many parcels face a street on each side --}}
                @php $maxSide = max(1, ...array_values($fr['by_side'])); @endphp
                <svg viewBox="-40 0 300 222" dir="ltr" class="w-full max-w-[19rem] mx-auto" role="img" aria-label="{{ __('portal.frontage_profile_title') }}">
                    <circle cx="110" cy="110" r="96" class="fill-surface-container-low dark:fill-white/5" />
                    <circle cx="110" cy="110" r="64" fill="none" class="stroke-outline-variant dark:stroke-white/10" stroke-dasharray="3 4" />
                    @foreach (['n' => [110, 110, 0], 'e' => [110, 110, 90], 's' => [110, 110, 180], 'w' => [110, 110, 270]] as $side => [$cx, $cy, $rot])
                        @php $len = 22 + 58 * $fr['by_side'][$side] / $maxSide; @endphp
                        <g transform="rotate({{ $rot }} 110 110)">
                            <rect x="98" y="{{ 110 - $len }}" width="24" height="{{ $len }}" rx="6" class="{{ $fr['by_side'][$side] > 0 ? 'fill-secondary' : 'fill-outline-variant dark:fill-white/20' }}" opacity="{{ $fr['by_side'][$side] > 0 ? 0.25 + 0.75 * $fr['by_side'][$side] / $maxSide : 1 }}" />
                        </g>
                    @endforeach
                    <circle cx="110" cy="110" r="16" class="fill-primary dark:fill-primary-fixed-dim" />
                    <text x="110" y="114" text-anchor="middle" font-size="11" class="fill-white dark:fill-primary">{{ $fr['surveyed'] }}</text>
                    @foreach (['n' => [110, 12], 's' => [110, 214], 'e' => [212, 114], 'w' => [8, 114]] as $side => [$tx, $ty])
                        <text x="{{ $tx }}" y="{{ $ty }}" text-anchor="middle" font-size="11" class="fill-on-surface dark:fill-white" font-weight="500">{{ __('portal.side.'.$side) }} · {{ $fr['by_side'][$side] }}</text>
                    @endforeach
                </svg>

                {{-- Lot type split --}}
                <div class="space-y-3">
                    @foreach ([
                        'three_plus' => ['icon' => 'hub', 'tone' => 'bg-tertiary-container'],
                        'corner' => ['icon' => 'turn_right', 'tone' => 'bg-secondary'],
                        'one' => ['icon' => 'straight', 'tone' => 'bg-primary dark:bg-primary-fixed-dim'],
                        'interior' => ['icon' => 'crop_square', 'tone' => 'bg-outline'],
                    ] as $type => $meta)
                        @php $n = $fr['by_count'][$type]; $pct = round($n / $fr['surveyed'] * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-on-surface-variant dark:text-on-primary-container">{{ $meta['icon'] }}</span>{{ __('portal.lot_'.$type) }}</span>
                                <span class="font-semibold data-tabular">{{ $n }} <span class="text-xs text-on-surface-variant dark:text-on-primary-container">({{ $pct }}%)</span></span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                                <div class="h-full rounded-full {{ $meta['tone'] }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Street facts --}}
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([
                        ['icon' => 'add_road', 'label' => __('portal.widest_street'), 'value' => $fr['widest_street'] ? rtrim(rtrim(number_format($fr['widest_street'], 1), '0'), '.').' '.__('portal.metre_short') : '—'],
                        ['icon' => 'straighten', 'label' => __('portal.total_frontage'), 'value' => number_format($fr['total_frontage']).' '.__('portal.metre_short')],
                        ['icon' => 'crop_din', 'label' => __('portal.regular_lots'), 'value' => $fr['regular'].' / '.$fr['surveyed']],
                        ['icon' => 'fact_check', 'label' => __('portal.matches_deed'), 'value' => $fr['matches_deed'].' / '.$pf['parcels']],
                    ] as $fact)
                        <div class="rounded-2xl p-3 bg-surface-container-low dark:bg-white/5">
                            <span class="material-symbols-outlined text-[20px] text-tertiary">{{ $fact['icon'] }}</span>
                            <p class="text-lg font-bold data-tabular mt-1">{{ $fact['value'] }}</p>
                            <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ $fact['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
    {{-- ══ Portfolio makeup: two gradient donuts and three ranked lists ══ --}}
    @php
        $palette = ['#002444', '#006c4e', '#c9a84c', '#68dbae', '#436084', '#755b00', '#abc9f2'];
        $donuts = [
            ['title' => __('dashboard.distribution_by_type'), 'icon' => 'category', 'rows' => $byAssetType],
            ['title' => __('dashboard.distribution_by_qrar_source'), 'icon' => 'gavel', 'rows' => $byQrarSource],
        ];
        $ranked = [
            ['title' => __('dashboard.distribution_by_city'), 'icon' => 'location_city', 'rows' => $byCity, 'bar' => 'from-secondary to-secondary-fixed-dim'],
            ['title' => __('dashboard.distribution_by_district'), 'icon' => 'holiday_village', 'rows' => $byDistrict, 'bar' => 'from-tertiary to-tertiary-container'],
            ['title' => __('dashboard.distribution_by_office'), 'icon' => 'engineering', 'rows' => $byEngineeringOffice, 'bar' => 'from-primary to-surface-tint'],
        ];
    @endphp
    <section>
        <div class="mb-4">
            <h2 class="text-lg font-bold flex items-center gap-2"><span class="material-symbols-outlined text-[22px] text-secondary">donut_small</span>{{ __('portal.dist_title') }}</h2>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.dist_hint') }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
            @foreach ($donuts as $d)
                @php $total = array_sum(array_column($d['rows'], 'parcels_count')); @endphp
                <div class="{{ $cardCls }} !p-6">
                    <h3 class="flex items-center gap-2 text-sm font-semibold mb-2">
                        <span class="w-8 h-8 rounded-xl bg-surface-container dark:bg-white/5 flex items-center justify-center"><span class="material-symbols-outlined text-[18px] text-secondary">{{ $d['icon'] }}</span></span>
                        {{ $d['title'] }}
                    </h3>
                    @if (empty($d['rows']))
                        <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-[13rem_1fr] gap-4 items-center">
                            <div wire:ignore x-data="{ init() {
                                themedChart(this.$refs.el, (dark) => ({
                                    chart: { type: 'donut', height: 220, background: 'transparent', fontFamily: '{{ $font }}', animations: { speed: 900 } },
                                    series: @js(array_column($d['rows'], 'parcels_count')),
                                    labels: @js(array_column($d['rows'], 'name')),
                                    colors: @js($palette),
                                    theme: { mode: dark ? 'dark' : 'light' },
                                    fill: { type: 'gradient', gradient: { shade: 'dark', type: 'diagonal1', opacityFrom: 1, opacityTo: 0.85 } },
                                    stroke: { width: 3, colors: [dark ? '#141b29' : '#ffffff'] },
                                    legend: { show: false },
                                    dataLabels: { enabled: false },
                                    plotOptions: { pie: { expandOnClick: true, donut: { size: '72%', labels: { show: true,
                                        value: { fontSize: '26px', fontWeight: 700 },
                                        total: { show: true, label: @js(__('dashboard.total_parcels')), fontSize: '12px', formatter: () => '{{ $total }}' } } } } },
                                }));
                            } }"><div x-ref="el"></div></div>

                            <ul class="space-y-3">
                                @foreach ($d['rows'] as $i => $row)
                                    @php $pct = $total > 0 ? round($row['parcels_count'] / $total * 100) : 0; @endphp
                                    <li>
                                        <div class="flex items-center justify-between gap-2 text-sm">
                                            <span class="flex items-center gap-2 min-w-0"><span class="w-3 h-3 rounded-full shrink-0" style="background: {{ $palette[$i % count($palette)] }}"></span><span class="truncate">{{ $row['name'] }}</span></span>
                                            <span class="data-tabular shrink-0"><span class="font-bold">{{ $row['parcels_count'] }}</span> <span class="text-xs text-on-surface-variant dark:text-on-primary-container">· {{ $pct }}%</span></span>
                                        </div>
                                        <div class="mt-1 h-1.5 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                                            <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $palette[$i % count($palette)] }}"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            @foreach ($ranked as $r)
                @php $top = max(1, ...array_column($r['rows'], 'parcels_count') ?: [1]); @endphp
                <div class="{{ $cardCls }} !p-6">
                    <h3 class="flex items-center gap-2 text-sm font-semibold mb-4">
                        <span class="w-8 h-8 rounded-xl bg-surface-container dark:bg-white/5 flex items-center justify-center"><span class="material-symbols-outlined text-[18px] text-tertiary">{{ $r['icon'] }}</span></span>
                        {{ $r['title'] }}
                    </h3>
                    @if (empty($r['rows']))
                        <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
                    @else
                        <ol class="space-y-3">
                            @foreach (array_slice($r['rows'], 0, 6) as $i => $row)
                                <li class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center text-xs font-bold data-tabular
                                                 {{ $i === 0 ? 'bg-gradient-to-br from-tertiary-fixed-dim to-tertiary text-white shadow-sm' : 'bg-surface-container dark:bg-white/10' }}">{{ $i + 1 }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2 text-sm">
                                            <span class="truncate" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                            <span class="font-bold data-tabular shrink-0">{{ $row['parcels_count'] }}</span>
                                        </div>
                                        <div class="mt-1 h-2 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-l {{ $r['bar'] }}" style="width: {{ round($row['parcels_count'] / $top * 100) }}%"></div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Owner's own portfolios --}}
    @if (! empty($ownerPortfolios))
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-4">
                {{ __('owners.portfolios') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($ownerPortfolios as $p)
                    <div class="{{ $cardCls }}">
                        <h3 class="font-semibold text-sm mb-2">{{ $p['name'] }}</h3>
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant dark:text-on-primary-container">
                            <span class="data-tabular">{{ $p['parcels'] }} {{ __('owners.portfolio_parcels_unit') }}</span>
                            <span class="data-tabular">{{ number_format($p['area'] / 1000000, 2) }} {{ __('dashboard.area_unit_km') }}</span>
                        </div>
                        @if ($p['value'] !== null)
                            <p class="text-sm font-bold text-secondary data-tabular mt-1">
                                {{ number_format($p['value'], 0) }}
                                <span class="text-[11px] font-normal text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.currency') }}</span>
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
    @vite(['resources/js/map.js', 'resources/js/portal-map-3d.js'])
@endpush
