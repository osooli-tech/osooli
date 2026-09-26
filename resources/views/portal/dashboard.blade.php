@extends('portal.layout')

@section('title', __('portal.dashboard_title'))

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-xl font-semibold">{{ __('portal.welcome', ['name' => $greetingName]) }}</h1>
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container mt-1">
            {{ __('portal.dashboard_subtitle') }}
        </p>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['label' => __('portal.kpi_parcels'), 'value' => $summary['parcels_total']],
            ['label' => __('portal.kpi_area'), 'value' => number_format($summary['area_total_sqm'], 0), 'unit' => __('dashboard.area_unit_sqm')],
            ['label' => __('portal.kpi_active_deeds'), 'value' => $summary['deeds_active']],
            ['label' => __('portal.kpi_documents'), 'value' => $summary['documents_count']],
        ] as $kpi)
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4 border border-outline-variant dark:border-white/10 shadow-sm">
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-1">{{ $kpi['label'] }}</p>
                <p class="text-2xl font-bold data-tabular">
                    {{ $kpi['value'] }}
                    @isset($kpi['unit'])
                        <span class="text-xs font-normal text-on-surface-variant dark:text-on-primary-container">{{ $kpi['unit'] }}</span>
                    @endisset
                </p>
            </div>
        @endforeach
    </div>

    {{-- Map — reuses map.js completely unmodified, pointed at the portal's
         own owner-scoped feed instead of the dashboard's. --}}
    <div class="rounded-2xl overflow-hidden shadow-sm border border-outline-variant dark:border-white/10" style="height: 520px;">
        <div id="sakuki-map"
             class="w-full h-full bg-surface-container-lowest dark:bg-[#1a1f2e]"
             data-token="{{ config('services.mapbox.token') }}"
             data-geojson-url="{{ route('portal.geo.parcels') }}"
             data-can-edit-colors="0">
            @if (! config('services.mapbox.token'))
                <div class="flex flex-col items-center justify-center h-full gap-3 text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[48px] opacity-40">map</span>
                    <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('scripts')
    @vite('resources/js/map.js')
@endpush
