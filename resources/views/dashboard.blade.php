@extends('layouts.app')

@section('title', __('dashboard.title'))
@section('page-title', __('dashboard.title'))

@section('content')
<div class="space-y-8">

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

    {{-- Map + parcel detail panel --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <div class="xl:col-span-2 rounded-2xl overflow-hidden shadow-sm
                    border border-outline-variant dark:border-white/10"
             style="height: 380px;">
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
        </div>

        <div x-data="{ parcel: null }"
             @parcel-selected.window="parcel = $event.detail"
             class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl shadow-sm
                    border border-outline-variant dark:border-white/10 flex flex-col"
             style="min-height: 200px;">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-outline-variant dark:border-white/10 shrink-0">
                <span class="material-symbols-outlined text-[18px] text-secondary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">pin_drop</span>
                <h3 class="text-sm font-semibold text-on-surface dark:text-white">
                    {{ __('dashboard.parcel_details') }}
                </h3>
            </div>
            <div class="flex-1 flex flex-col justify-center p-5">
                <template x-if="! parcel">
                    <div class="flex flex-col items-center justify-center gap-3
                                text-on-surface-variant dark:text-on-primary-container text-sm text-center">
                        <span class="material-symbols-outlined text-[40px] opacity-30">touch_app</span>
                        <p>{{ __('dashboard.click_parcel') }}</p>
                    </div>
                </template>
                <template x-if="parcel">
                    <dl class="space-y-4 text-sm">
                        <div class="flex justify-between items-start gap-2">
                            <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.parcel_no') }}</dt>
                            <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end" x-text="parcel.parcel_no ?? '—'"></dd>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.geo_id') }}</dt>
                            <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end text-xs break-all" x-text="parcel.geo_id ?? '—'"></dd>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.asset_type') }}</dt>
                            <dd class="font-semibold text-on-surface dark:text-white text-end" x-text="parcel.asset_type ?? '—'"></dd>
                        </div>
                    </dl>
                </template>
            </div>
        </div>

    </div>

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
