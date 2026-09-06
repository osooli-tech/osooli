@extends('layouts.app')

@section('title', __('parcels.twin_title'))

@php
    $id = $twin['identity'];
    $area = $twin['area'];
    $value = $twin['valuation'];
    $owners = $twin['owners'];
    $docs = $twin['documents'];
    $done = $twin['completeness'];

    // A gap worth surfacing: the deed and the geometry describe the same land.
    $areaGap = $area['deed'] && $area['computed']
        ? abs($area['deed'] - $area['computed']) / $area['deed']
        : null;
@endphp

@section('content')

<div x-data="{ tab: 'overview' }">

    {{-- Back link --}}
    <a href="{{ route('parcels.show', $parcel) }}"
       class="inline-flex items-center gap-1 text-sm text-on-surface-variant dark:text-on-primary-container
              hover:text-secondary mb-4">
        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
        {{ __('parcels.back_to_parcel') }}
    </a>

    {{-- ── Header card ──────────────────────────────────────────────── --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden mb-5">

        <div class="p-5 border-b border-outline-variant dark:border-white/10">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="material-symbols-outlined text-[20px] text-tertiary"
                              style="font-variation-settings: 'FILL' 1;">deployed_code</span>
                        <h1 class="text-lg font-bold text-on-surface dark:text-white">
                            {{ __('parcels.twin_title') }}
                        </h1>
                    </div>
                    <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
                        {{ __('parcels.twin_subtitle') }}
                    </p>
                </div>

                {{-- Completeness meter: an honest reading of the file itself --}}
                <div class="shrink-0 min-w-[190px]">
                    <div class="flex items-baseline justify-between gap-2 mb-1.5">
                        <span class="text-xs text-on-surface-variant dark:text-on-primary-container">
                            {{ __('parcels.completeness') }}
                        </span>
                        <span class="text-sm font-bold text-on-surface dark:text-white data-tabular">
                            {{ $done['percent'] }}%
                        </span>
                    </div>
                    <div class="h-1.5 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full {{ $done['percent'] >= 70 ? 'bg-secondary' : ($done['percent'] >= 40 ? 'bg-tertiary' : 'bg-error') }}"
                             style="width: {{ $done['percent'] }}%"></div>
                    </div>
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1 data-tabular">
                        {{ __('parcels.completeness_hint', ['total' => $done['total']]) }} — {{ $done['filled'] }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Identity strip --}}
        <dl class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-x-5 gap-y-3 p-5 text-sm">
            @foreach ([
                'parcels.parcel_no'  => $id['parcel_no'],
                'parcels.spatial_id' => $id['spatial_id'],
                'parcels.plan_no'    => $id['plan_no'],
                'parcels.district'   => $id['district'],
                'parcels.city'       => $id['city'],
                'parcels.asset_type' => $id['asset_type'],
            ] as $key => $val)
                <div class="min-w-0">
                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">{{ __($key) }}</dt>
                    <dd class="font-semibold text-on-surface dark:text-white truncate
                               {{ in_array($key, ['parcels.parcel_no', 'parcels.spatial_id', 'parcels.plan_no'], true) ? 'data-tabular' : '' }}">
                        {{ $val ?: '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- ── Tabs ─────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 mb-5 border-b border-outline-variant dark:border-white/10 overflow-x-auto">
        @foreach ([
            'overview'  => ['label' => 'parcels.tab_overview',  'icon' => 'dashboard'],
            'deed'      => ['label' => 'parcels.tab_deed',      'icon' => 'description'],
            'boundary'  => ['label' => 'parcels.tab_boundary',  'icon' => 'straighten'],
            'documents' => ['label' => 'parcels.tab_documents', 'icon' => 'folder'],
        ] as $key => $meta)
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}'
                        ? 'border-secondary text-secondary'
                        : 'border-transparent text-on-surface-variant dark:text-on-primary-container hover:text-on-surface dark:hover:text-white'"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px
                           whitespace-nowrap transition-colors">
                <span class="material-symbols-outlined text-[18px]">{{ $meta['icon'] }}</span>
                {{ __($meta['label']) }}
            </button>
        @endforeach
    </div>

    {{-- ── Overview ─────────────────────────────────────────────────── --}}
    <div x-show="tab === 'overview'" x-cloak class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- The three areas, side by side --}}
        <div class="xl:col-span-2 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.area_section') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach ([
                    ['parcels.area_deed_label',     $area['deed'],     null],
                    ['parcels.area_measured_label', $area['measured'], null],
                    ['parcels.area_computed_label', $area['computed'], __('parcels.area_computed_hint')],
                ] as [$label, $val, $hint])
                    <div class="bg-surface-container dark:bg-white/5 rounded-xl p-3">
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-1">{{ __($label) }}</p>
                        <p class="text-lg font-bold text-on-surface dark:text-white data-tabular leading-none">
                            @if ($val !== null)
                                {{ number_format($val, 0) }} <span class="text-xs font-normal">م²</span>
                            @else
                                <span class="text-sm font-normal text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.not_recorded') }}
                                </span>
                            @endif
                        </p>
                        @if ($hint)
                            <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1">{{ $hint }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($areaGap !== null && $areaGap > 0.05)
                <div class="mt-3 flex items-start gap-2 text-xs bg-tertiary-container/40 dark:bg-tertiary/10
                            border-s-[3px] border-tertiary rounded-lg p-2.5">
                    <span class="material-symbols-outlined text-[16px] text-tertiary shrink-0">info</span>
                    <span class="text-on-surface dark:text-white">
                        {{ __('parcels.area_mismatch') }} — {{ number_format($areaGap * 100, 1) }}%
                    </span>
                </div>
            @endif
        </div>

        {{-- Valuation --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.value_total') }}</h2>

            @if ($value['total'] !== null)
                <p class="text-2xl font-bold text-secondary data-tabular leading-none mb-1">
                    {{ number_format($value['total'], 0) }}
                    <span class="text-sm font-normal text-on-surface-variant dark:text-on-primary-container">
                        {{ __('parcels.currency') }}
                    </span>
                </p>
                @if ($value['per_metre'] !== null)
                    <p class="text-sm text-on-surface-variant dark:text-on-primary-container data-tabular mt-2">
                        {{ __('parcels.value_per_metre') }}:
                        <span class="font-semibold text-on-surface dark:text-white">
                            {{ number_format($value['per_metre'], 0) }}
                        </span>
                    </p>
                @endif
                @if ($value['is_derived'])
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-2">
                        {{ __('parcels.value_derived') }}
                    </p>
                @endif
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @endif
        </div>

        {{-- Missing fields, named rather than hinted at --}}
        @if ($done['missing']->isNotEmpty())
            <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                        border border-outline-variant dark:border-white/10 shadow-sm">
                <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-3">{{ __('parcels.missing_fields') }}</h2>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($done['missing'] as $field)
                        <span class="text-xs bg-surface-container dark:bg-white/5 text-on-surface-variant
                                     dark:text-on-primary-container rounded-full px-2.5 py-1">
                            {{ __('parcels.'.$field) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Map — lives on this tab directly (it used to be its own "الموقع"
             tab holding nothing else); "overview" is the tab active on page
             load, so Mapbox measures a real container size at init. --}}
        <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl overflow-hidden
                    border border-outline-variant dark:border-white/10 shadow-sm h-[420px] relative"
             @if ($parcelGeojson && config('services.mapbox.token'))
                 x-data="parcelMiniMap(@js($parcelGeojson), @js(json_encode(['type' => 'FeatureCollection', 'features' => []])), @js($parcel->parcel_no))"
                 x-init="init()"
             @endif>

            @if ($parcelGeojson && config('services.mapbox.token'))
                <div id="parcel-mini-map" class="absolute inset-0 w-full h-full"></div>

                @if ($centroid)
                    <div class="absolute bottom-3 start-3 z-10 bg-surface-container-lowest/90 dark:bg-[#1a1f2e]/90
                                backdrop-blur rounded-lg px-3 py-2 text-xs data-tabular ltr" dir="ltr">
                        {{ number_format($centroid['lat'], 6) }}, {{ number_format($centroid['lng'], 6) }}
                    </div>
                @endif
            @else
                <div class="flex flex-col items-center justify-center h-full gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[40px] opacity-30">map</span>
                    <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
                </div>
            @endif
        </div>

        <p class="xl:col-span-3 text-xs text-on-surface-variant dark:text-on-primary-container">
            {{ __('parcels.twin_source_note') }}
        </p>
    </div>

    {{-- ── Deed & ownership ─────────────────────────────────────────── --}}
    <div x-show="tab === 'deed'" x-cloak class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.deeds_section') }}</h2>

            @forelse ($parcel->deeds as $deed)
                <div class="border border-outline-variant dark:border-white/10 rounded-xl p-3 mb-2 last:mb-0">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        @foreach ([
                            'parcels.deed_no'     => $deed->deed_no,
                            'parcels.deed_date'   => $deed->deed_date_hijri,
                            'parcels.deed_status' => $deed->deed_status,
                            'parcels.deed_class'  => $deed->deed_class,
                        ] as $label => $val)
                            <div class="flex justify-between gap-2">
                                <dt class="text-on-surface-variant dark:text-on-primary-container">{{ __($label) }}</dt>
                                <dd class="font-semibold text-on-surface dark:text-white text-end
                                           {{ str_contains($label, 'no') || str_contains($label, 'date') ? 'data-tabular' : '' }}">
                                    {{ $val ?: '—' }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @endforelse
        </div>

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">{{ __('parcels.owners_section') }}</h2>
                @if (count($owners) > 1)
                    <span class="text-xs bg-tertiary-container text-on-tertiary-container rounded-full px-2.5 py-0.5">
                        {{ __('parcels.co_owned') }} — {{ count($owners) }}
                    </span>
                @endif
            </div>

            @forelse ($owners as $owner)
                <div class="flex items-start justify-between gap-3 py-2.5
                            border-b border-outline-variant dark:border-white/10 last:border-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-on-surface dark:text-white text-sm">{{ $owner['name'] }}</p>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular ltr" dir="ltr">
                            {{ $owner['national_id'] ?: '—' }}
                        </p>
                    </div>
                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container shrink-0 data-tabular">
                        {{ $owner['share'] ?: __('parcels.share_unrecorded') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ── Boundaries & survey decision ─────────────────────────────── --}}
    <div x-show="tab === 'boundary'" x-cloak class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.boundary_section') }}</h2>

            @if ($parcel->boundary)
                @php $b = $parcel->boundary; @endphp
                {{-- dir=ltr pins the grid geographically: west left, east right. --}}
                <div class="grid grid-cols-3 gap-2 text-center text-xs" dir="ltr">
                    <div></div>
                    <x-boundary-cell :label="__('parcels.n_border')" :value="$b->n_border" :dim="$b->n_dim" />
                    <div></div>

                    <x-boundary-cell :label="__('parcels.w_border')" :value="$b->w_border" :dim="$b->w_dim" />
                    <div class="flex flex-col items-center justify-center gap-1">
                        <svg width="28" height="28" viewBox="0 0 28 28" class="shrink-0">
                            <polygon points="14,2 18,16 14,12 10,16" fill="#006c4e" opacity="0.9"/>
                            <polygon points="14,26 18,12 14,16 10,12" fill="#9e9e9e" opacity="0.45"/>
                        </svg>
                        <span class="text-[10px] font-bold text-secondary">ش</span>
                    </div>
                    <x-boundary-cell :label="__('parcels.e_border')" :value="$b->e_border" :dim="$b->e_dim" />

                    <div></div>
                    <x-boundary-cell :label="__('parcels.s_border')" :value="$b->s_border" :dim="$b->s_dim" />
                    <div></div>
                </div>

                @if ($b->engineeringOffice)
                    <div class="flex justify-between text-sm mt-4 pt-3 border-t border-outline-variant dark:border-white/10">
                        <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.engineering_office') }}</span>
                        <span class="font-medium text-on-surface dark:text-white text-end">{{ $b->engineeringOffice->name }}</span>
                    </div>
                @endif
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @endif
        </div>

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.survey_section') }}</h2>

            @forelse ($parcel->surveyDecisions as $decision)
                <dl class="space-y-2.5 text-sm">
                    @foreach ([
                        'parcels.qrar_no'     => $decision->qrar_no,
                        'parcels.report_no'   => $decision->report_no,
                        'parcels.qrar_source' => $decision->qrar_source?->value,
                        'parcels.folder'      => $decision->folder,
                    ] as $label => $val)
                        <div class="flex justify-between gap-3">
                            <dt class="text-on-surface-variant dark:text-on-primary-container">{{ __($label) }}</dt>
                            <dd class="font-semibold text-on-surface dark:text-white text-end">{{ $val ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @endforelse

            {{-- The decision's reference numbers are often blank (not entered
                 at the source), but the survey card itself may still be on
                 file — link to it here rather than only under "الوثائق". --}}
            @if ($docs['survey']->isNotEmpty())
                <div class="mt-4 pt-3 border-t border-outline-variant dark:border-white/10">
                    @foreach ($docs['survey'] as $file)
                        <a href="{{ $file->photo_url }}" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface-container dark:hover:bg-white/5
                                  border border-outline-variant dark:border-white/10 mb-2 last:mb-0 transition-colors">
                            <span class="material-symbols-outlined text-[20px] text-error shrink-0">picture_as_pdf</span>
                            <span class="flex-1 min-w-0 text-sm text-on-surface dark:text-white truncate">
                                {{ $file->photo_type?->value }}
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant dark:text-on-primary-container shrink-0">open_in_new</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ── Documents & images ───────────────────────────────────────── --}}
    <div x-show="tab === 'documents'" x-cloak class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.documents_section') }}</h2>

            @php
                // Deed scans sort current-first: the file someone actually
                // needs is the one for the deed that's still valid.
                $files = $docs['deed']
                    ->sortByDesc(fn ($file) => $file->deed_id === $parcel->currentDeed?->id)
                    ->concat($docs['survey']);
            @endphp

            @forelse ($files as $file)
                @php
                    $isOldDeedScan = $file->photo_type === \App\Enums\PhotoType::Deed
                        && $file->deed_id !== null
                        && $file->deed_id !== $parcel->currentDeed?->id;
                @endphp
                <a href="{{ $file->photo_url }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface-container dark:hover:bg-white/5
                          border border-outline-variant dark:border-white/10 mb-2 last:mb-0 transition-colors">
                    <span class="material-symbols-outlined text-[20px] text-error shrink-0">picture_as_pdf</span>
                    <span class="flex-1 min-w-0 text-sm text-on-surface dark:text-white truncate">
                        {{ $file->photo_type?->value }}
                        @if ($file->deed?->deed_no)
                            <span class="text-on-surface-variant dark:text-on-primary-container font-normal">— {{ $file->deed->deed_no }}</span>
                        @endif
                    </span>
                    @if ($isOldDeedScan)
                        <span class="shrink-0 text-[10px] font-medium px-2 py-0.5 rounded-full
                                     bg-tertiary-container/20 text-tertiary-container dark:bg-tertiary-container/30">
                            {{ __('parcels.old_deed_badge') }}
                        </span>
                    @endif
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant dark:text-on-primary-container shrink-0">
                        download
                    </span>
                </a>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.no_documents') }}</p>
            @endforelse
        </div>

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.photos_section') }}</h2>

            @if ($docs['images']->isNotEmpty())
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($docs['images'] as $image)
                        <a href="{{ $image->photo_url }}" target="_blank" rel="noopener"
                           class="aspect-square rounded-xl overflow-hidden bg-surface-container dark:bg-white/5">
                            <img src="{{ $image->photo_url }}" alt="{{ $image->photo_type?->value }}"
                                 loading="lazy" class="w-full h-full object-cover" />
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.no_images') }}</p>
            @endif
        </div>
    </div>

    {{-- ── Location ─────────────────────────────────────────────────── --}}
    {{-- x-if, not x-show: Mapbox measures its container on init, and a hidden
         tab has no size. Building the map only once the tab is open avoids a
         zero-height canvas — and a second map being created on top of the first. --}}
    <template x-if="tab === 'location'">
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl overflow-hidden
                    border border-outline-variant dark:border-white/10 shadow-sm h-[520px] relative"
             @if ($parcelGeojson && config('services.mapbox.token'))
                 x-data="parcelMiniMap(@js($parcelGeojson), @js(json_encode(['type' => 'FeatureCollection', 'features' => []])), @js($parcel->parcel_no))"
             @endif>

            @if ($parcelGeojson && config('services.mapbox.token'))
                <div id="parcel-mini-map" class="absolute inset-0 w-full h-full"></div>

                @if ($centroid)
                    <div class="absolute bottom-3 start-3 z-10 bg-surface-container-lowest/90 dark:bg-[#1a1f2e]/90
                                backdrop-blur rounded-lg px-3 py-2 text-xs data-tabular ltr" dir="ltr">
                        {{ number_format($centroid['lat'], 6) }}, {{ number_format($centroid['lng'], 6) }}
                    </div>
                @endif
            @else
                <div class="flex flex-col items-center justify-center h-full gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[40px] opacity-30">map</span>
                    <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
                </div>
            @endif
        </div>
    </template>

</div>

@push('scripts')
@include('parcels.partials.mini-map-script')
@endpush

@endsection
