@extends('layouts.app')

@section('title', __('dashboard.title'))
@section('page-title', __('dashboard.title'))

@section('content')
<div class="space-y-8">

    {{-- Map + parcel detail panel (map ≈70%, panel ≈30%) --}}
    <div class="grid grid-cols-1 xl:grid-cols-10 gap-5"
         x-data="{
            parcel: null,
            documents: [],
            documentsLoading: false,
            fetchDocuments(id) {
                this.documentsLoading = true;
                this.documents = [];
                fetch(`/parcels/${id}/documents`)
                    .then((r) => r.json())
                    .then((data) => { this.documents = data.documents ?? []; })
                    .finally(() => { this.documentsLoading = false; });
            },
            deedDocument() {
                return this.documents.find((d) => d.type === 'صك') ?? null;
            },
            otherDocuments() {
                return this.documents.filter((d) => d.type !== 'صك');
            },
         }"
         @parcel-selected.window="parcel = $event.detail; fetchDocuments(parcel.id)">

        <div class="xl:col-span-7 relative rounded-2xl overflow-hidden shadow-sm
                    border border-outline-variant dark:border-white/10"
             style="height: 620px;">
            <div id="sakuki-map"
                 class="w-full h-full bg-surface-container-lowest dark:bg-[#1a1f2e]"
                 data-token="{{ config('services.mapbox.token') }}"
                 data-geojson-url="{{ route('geo.parcels') }}">
                @if (! config('services.mapbox.token'))
                    <div class="flex flex-col items-center justify-center h-full gap-3
                                text-on-surface-variant dark:text-on-primary-container">
                        <span class="material-symbols-outlined text-[48px] opacity-40">map</span>
                        <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
                    </div>
                @endif
            </div>

            {{-- Search box --}}
            <div class="absolute top-3 inset-x-3 z-10 max-w-sm">
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px] text-on-surface-variant pointer-events-none">search</span>
                    <input id="map-search"
                           type="text"
                           placeholder="{{ __('dashboard.search_map_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2.5 text-sm rounded-xl shadow-md
                                  bg-white/95 dark:bg-[#1a1f2e]/95 backdrop-blur
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            {{-- Layer controls. Collapsed to a button until opened, so the panel
                 never covers the map while nobody is using it. --}}
            <div class="absolute top-3 end-3 z-10" x-data="{ open: false }">

                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium shadow-md
                               bg-white/95 dark:bg-[#1a1f2e]/95 backdrop-blur border border-outline-variant
                               dark:border-white/10 text-on-surface dark:text-white
                               hover:bg-white dark:hover:bg-[#1a1f2e] transition-colors">
                    <span class="material-symbols-outlined text-[16px]">layers</span>
                    {{ __('dashboard.map_layers') }}
                </button>

                <div x-show="open" x-cloak @click.outside="open = false"
                     class="mt-2 w-60 max-h-[70vh] overflow-y-auto rounded-xl shadow-lg p-3 space-y-4
                            bg-white/97 dark:bg-[#1a1f2e]/97 backdrop-blur
                            border border-outline-variant dark:border-white/10">

                    {{-- Colour the parcels by one attribute at a time --}}
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide
                                  text-on-surface-variant dark:text-on-primary-container mb-1.5">
                            {{ __('dashboard.colour_by') }}
                        </p>
                        <div class="space-y-0.5">
                            @foreach ([
                                'none' => 'dashboard.colour_none',
                                'deed_status' => 'parcels.deed_status',
                                'asset_type' => 'parcels.asset_type',
                                'priced' => 'dashboard.colour_priced',
                            ] as $mode => $label)
                                <label class="flex items-center gap-2 px-1.5 py-1 rounded-lg cursor-pointer text-xs
                                              text-on-surface dark:text-white hover:bg-surface-container dark:hover:bg-white/5">
                                    <input type="radio" name="parcel-colour" value="{{ $mode }}"
                                           @checked($mode === 'none')
                                           class="accent-secondary w-3.5 h-3.5 shrink-0">
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Legend, rebuilt by map.js whenever the colouring changes --}}
                    <div id="map-legend" class="hidden">
                        <p class="text-[10px] font-semibold uppercase tracking-wide
                                  text-on-surface-variant dark:text-on-primary-container mb-1.5">
                            {{ __('dashboard.legend') }}
                        </p>
                        <div id="map-legend-items" class="space-y-1"></div>
                    </div>

                    {{-- Individual layers --}}
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide
                                  text-on-surface-variant dark:text-on-primary-container mb-1.5">
                            {{ __('dashboard.visible_layers') }}
                        </p>
                        <div class="space-y-0.5">
                            @foreach ([
                                'parcels-fill' => ['dashboard.layer_parcels', 'category'],
                                'parcels-outline' => ['dashboard.layer_outlines', 'pentagon'],
                                'parcels-labels' => ['dashboard.show_labels', 'label'],
                            ] as $layer => [$label, $icon])
                                <label class="flex items-center gap-2 px-1.5 py-1 rounded-lg cursor-pointer text-xs
                                              text-on-surface dark:text-white hover:bg-surface-container dark:hover:bg-white/5">
                                    <input type="checkbox" checked data-layer="{{ $layer }}"
                                           class="accent-secondary w-3.5 h-3.5 shrink-0">
                                    <span class="material-symbols-outlined text-[15px]">{{ $icon }}</span>
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Basemap --}}
                    <div class="pt-1 border-t border-outline-variant dark:border-white/10">
                        <button id="toggle-basemap" type="button"
                                class="flex items-center gap-2 w-full px-1.5 py-1.5 rounded-lg text-xs font-medium
                                       text-on-surface dark:text-white hover:bg-surface-container
                                       dark:hover:bg-white/5 transition-colors">
                            <span class="material-symbols-outlined text-[16px]">satellite_alt</span>
                            <span id="basemap-label"
                                  data-satellite-label="{{ __('dashboard.satellite_view') }}"
                                  data-street-label="{{ __('dashboard.street_view') }}">{{ __('dashboard.satellite_view') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl shadow-sm
                    border border-outline-variant dark:border-white/10 flex flex-col overflow-hidden"
             style="height: 620px;">
            <div class="flex items-center justify-between gap-2 px-5 py-4 border-b border-outline-variant dark:border-white/10 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-secondary"
                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">pin_drop</span>
                    <h3 class="text-sm font-semibold text-on-surface dark:text-white">
                        {{ __('dashboard.parcel_details') }}
                    </h3>
                </div>
                <button type="button" x-show="parcel" @click="parcel = null"
                        class="text-on-surface-variant dark:text-on-primary-container hover:text-error transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
                <template x-if="! parcel">
                    <div class="h-full flex flex-col items-center justify-center gap-3
                                text-on-surface-variant dark:text-on-primary-container text-sm text-center">
                        <span class="material-symbols-outlined text-[40px] opacity-30">touch_app</span>
                        <p>{{ __('dashboard.click_parcel') }}</p>
                    </div>
                </template>

                <template x-if="parcel">
                    <div class="space-y-6 text-sm">

                        {{-- Parcel and deed identifiers --}}
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('parcels.parcel_info') }}
                            </p>
                            <dl class="space-y-2.5">
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.parcel_no') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end" x-text="parcel.parcel_no ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.spatial_id') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end ltr" dir="ltr" x-text="parcel.geo_id ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.asset_type') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white text-end" x-text="parcel.asset_type ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.plan_no') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end" x-text="parcel.plan_no ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.district') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white text-end" x-text="parcel.district_name ?? '—'"></dd>
                                </div>
                                {{-- A parcel may be co-owned, so this can list several names --}}
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.owner') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white text-end" x-text="parcel.owner_names ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_no') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end" x-text="parcel.deed_no ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_date') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end" x-text="parcel.deed_date_hijri ?? '—'"></dd>
                                </div>
                                <div class="flex justify-between items-start gap-2">
                                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_status') }}</dt>
                                    <dd class="font-semibold text-on-surface dark:text-white text-end" x-text="parcel.deed_status ?? '—'"></dd>
                                </div>
                            </dl>
                        </div>

                        {{-- المساحة --}}
                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('parcels.area_deed') }}
                            </p>
                            <p class="font-semibold text-on-surface dark:text-white data-tabular"
                               x-text="parcel.deed_area ? Number(parcel.deed_area).toLocaleString() + ' {{ __('dashboard.area_unit_sqm') }}' : '—'"></p>
                        </div>

                        {{-- الموقع والإحداثيات --}}
                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('dashboard.coordinates') }}
                            </p>
                            <p class="font-semibold text-on-surface dark:text-white data-tabular ltr mb-3" dir="ltr"
                               x-text="(parcel.centroid_lat && parcel.centroid_lng) ? Number(parcel.centroid_lat).toFixed(6) + ', ' + Number(parcel.centroid_lng).toFixed(6) : '—'"></p>

                            {{-- إحداثيات أركان القطعة --}}
                            <template x-if="parcel.corners && parcel.corners.length">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-1.5">
                                        {{ __('dashboard.corner_coordinates') }}
                                    </p>
                                    <ul class="space-y-1">
                                        <template x-for="(corner, i) in parcel.corners" :key="i">
                                            <li class="flex items-center justify-between gap-2 text-xs">
                                                <span class="text-on-surface-variant dark:text-on-primary-container shrink-0"
                                                      x-text="'{{ __('dashboard.corner') }} ' + (i + 1)"></span>
                                                <span class="font-medium text-on-surface dark:text-white data-tabular ltr" dir="ltr"
                                                      x-text="Number(corner.lat).toFixed(6) + ', ' + Number(corner.lng).toFixed(6)"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>

                        {{-- ملف الصك --}}
                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('dashboard.deed_document') }}
                            </p>
                            <template x-if="documentsLoading">
                                <p class="text-on-surface-variant dark:text-on-primary-container text-xs">…</p>
                            </template>
                            <template x-if="! documentsLoading && ! deedDocument()">
                                <p class="text-on-surface-variant dark:text-on-primary-container text-xs">{{ __('dashboard.deed_not_available') }}</p>
                            </template>
                            <div class="flex items-center gap-2" x-show="! documentsLoading && deedDocument()">
                                <a :href="deedDocument()?.download_url" target="_blank"
                                   class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                          bg-primary text-white hover:opacity-90 transition-opacity">
                                    <span class="material-symbols-outlined text-[15px]">visibility</span>
                                    {{ __('dashboard.view_deed') }}
                                </a>
                                <a :href="deedDocument()?.download_url" download
                                   class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                          bg-secondary text-white hover:opacity-90 transition-opacity">
                                    <span class="material-symbols-outlined text-[15px]">download</span>
                                    {{ __('dashboard.download_deed') }}
                                </a>
                            </div>
                        </div>

                        {{-- المستندات المرتبطة --}}
                        <div class="pt-4 border-t border-outline-variant dark:border-white/10">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                {{ __('dashboard.related_documents') }}
                            </p>
                            <template x-if="documentsLoading">
                                <p class="text-on-surface-variant dark:text-on-primary-container text-xs">…</p>
                            </template>
                            <template x-if="! documentsLoading && otherDocuments().length === 0">
                                <p class="text-on-surface-variant dark:text-on-primary-container text-xs">{{ __('dashboard.no_documents') }}</p>
                            </template>
                            <ul class="space-y-1.5" x-show="! documentsLoading && otherDocuments().length > 0">
                                <template x-for="doc in otherDocuments()" :key="doc.id">
                                    <li>
                                        <a :href="doc.download_url"
                                           class="flex items-center gap-2 text-primary hover:underline underline-offset-2 text-xs">
                                            <span class="material-symbols-outlined text-[15px]">description</span>
                                            <span x-text="doc.type ?? '—'"></span>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        {{-- Link to full detail page --}}
                        <a :href="parcel.id ? '/parcels/' + parcel.id : '#'"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl text-sm font-medium
                                  bg-primary text-white hover:opacity-90 transition-opacity">
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                            {{ __('dashboard.view_full_details') }}
                        </a>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- Section 1: KPI cards --}}
    <section>
        <h2 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest
                   text-on-surface-variant dark:text-on-primary-container mb-4">
            <span class="material-symbols-outlined text-[16px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;">
                bar_chart_4_bars
            </span>
            {{ __('dashboard.section_kpi') }}
        </h2>
        <livewire:dashboard.kpi-cards />
    </section>

    {{-- Section 2: distribution charts --}}
    <section>
        <h2 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest
                   text-on-surface-variant dark:text-on-primary-container mb-4">
            <span class="material-symbols-outlined text-[16px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;">
                donut_large
            </span>
            {{ __('dashboard.section_charts') }}
        </h2>
        <livewire:dashboard.distribution-charts />
    </section>

    {{-- Portfolios by city --}}
    <section>
        <h2 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest
                   text-on-surface-variant dark:text-on-primary-container mb-4">
            <span class="material-symbols-outlined text-[16px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;">
                account_balance_wallet
            </span>
            {{ __('dashboard.section_portfolios') }}
        </h2>
        <livewire:dashboard.city-portfolios />
    </section>

    {{-- Section 3: operational widgets --}}
    <section>
        <h2 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest
                   text-on-surface-variant dark:text-on-primary-container mb-4">
            <span class="material-symbols-outlined text-[16px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24;">
                monitor_heart
            </span>
            {{ __('dashboard.section_operational') }}
        </h2>
        <livewire:dashboard.operational-widgets />
    </section>

    {{-- Recent parcels + deed alerts --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2">
            <livewire:dashboard.recent-parcels />
        </div>
        <div>
            <livewire:dashboard.recent-alerts />
        </div>
    </div>

</div>
@endsection

@push('scripts')
    {{-- Load mapbox-gl from CDN to avoid Vite WebWorker bundling issues (v3) --}}
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet" />
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    @vite('resources/js/map.js')
@endpush
