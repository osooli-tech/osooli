@extends('portal.layout')

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
    <a href="{{ route('portal.parcels.show', $parcel) }}"
       class="inline-flex items-center gap-1 text-sm text-on-surface-variant dark:text-on-primary-container
              hover:text-secondary mb-4">
        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
        {{ __('parcels.back_to_parcel') }}
    </a>

    {{-- ── Header card ──────────────────────────────────────────────── --}}
    @php $ringLen = 2 * M_PI * 42; @endphp
    <x-portal.page-hero :title="null" id="twin" class="mb-5">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-24 h-24 rounded-3xl bg-white/5 ring-1 ring-white/15 flex items-center justify-center shrink-0">
                    <x-parcel-shape :geojson="$parcelGeojson" class="w-20 h-20 [&_path]:fill-secondary-fixed-dim/30 [&_path]:stroke-secondary-fixed" />
                </div>
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-sm text-tertiary-fixed-dim">
                        <span class="material-symbols-outlined text-[18px]">deployed_code</span>{{ __('parcels.twin_title') }}
                    </p>
                    <h1 class="text-3xl font-bold data-tabular">{{ __('parcels.parcel_no') }} {{ $id['parcel_no'] }}</h1>
                    <p class="text-sm text-primary-fixed-dim">{{ __('parcels.twin_subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative w-24 h-24">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90" aria-hidden="true">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="9" class="text-white/10" />
                        <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"
                                class="{{ $done['percent'] >= 70 ? 'text-secondary-fixed' : ($done['percent'] >= 40 ? 'text-tertiary-container' : 'text-error-container') }}"
                                stroke-dasharray="{{ round($ringLen * $done['percent'] / 100, 2) }} {{ round($ringLen, 2) }}" />
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-2xl font-bold data-tabular">{{ $done['percent'] }}%</span>
                </div>
                <div>
                    <p class="font-semibold">{{ __('parcels.completeness') }}</p>
                    <p class="text-xs text-primary-fixed-dim data-tabular">{{ __('parcels.completeness_hint', ['total' => $done['total']]) }} — {{ $done['filled'] }}</p>
                </div>
            </div>
        </div>
    </x-portal.page-hero>

    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden mb-5">
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
            'story'     => ['label' => 'portal.tab_story',       'icon' => 'history_edu'],
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

        <div class="xl:col-span-2 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.area_section') }}</h2>

            @php
                $areaVals = array_filter(['deed' => $area['deed'], 'measured' => $area['measured'], 'computed' => $area['computed']], fn ($v) => $v !== null && $v > 0);
                $areaMax = $areaVals ? max($areaVals) : 1;
                $sq = fn (float $v): float => 150 * sqrt($v / $areaMax);
                $areaStyle = [
                    'deed' => ['stroke' => 'stroke-primary dark:stroke-primary-fixed-dim', 'fill' => 'fill-primary/10', 'dash' => '', 'dot' => 'bg-primary dark:bg-primary-fixed-dim', 'label' => 'parcels.area_deed_label'],
                    'measured' => ['stroke' => 'stroke-tertiary-container', 'fill' => 'fill-tertiary-container/10', 'dash' => '6 4', 'dot' => 'bg-tertiary-container', 'label' => 'parcels.area_measured_label'],
                    'computed' => ['stroke' => 'stroke-secondary', 'fill' => 'fill-secondary/10', 'dash' => '2 3', 'dot' => 'bg-secondary', 'label' => 'parcels.area_computed_label'],
                ];
            @endphp
            {{-- Three squares, each sized to one area and centred on the others — a mismatch shows as a visible gap --}}
            <div class="grid grid-cols-1 sm:grid-cols-[14rem_1fr] gap-5 items-center">
                <svg viewBox="0 0 170 170" class="w-full max-w-[14rem] mx-auto" aria-hidden="true">
                    @foreach ($areaStyle as $key => $st)
                        @if (isset($areaVals[$key]))
                            @php $side = $sq($areaVals[$key]); $o = (170 - $side) / 2; @endphp
                            <rect x="{{ round($o, 2) }}" y="{{ round($o, 2) }}" width="{{ round($side, 2) }}" height="{{ round($side, 2) }}" rx="4"
                                  class="{{ $st['stroke'] }} {{ $st['fill'] }}" stroke-width="2.5" @if ($st['dash']) stroke-dasharray="{{ $st['dash'] }}" @endif />
                        @endif
                    @endforeach
                </svg>
                <ul class="space-y-3">
                    @foreach ($areaStyle as $key => $st)
                        <li class="flex items-center justify-between gap-3 rounded-xl p-3 bg-surface-container-low dark:bg-white/5">
                            <span class="flex items-center gap-2 text-sm"><span class="w-3 h-3 rounded-sm {{ $st['dot'] }}"></span>{{ __($st['label']) }}</span>
                            <span class="font-bold data-tabular">{{ isset($areaVals[$key]) ? number_format($areaVals[$key]).' '.__('dashboard.area_unit_sqm') : __('parcels.not_recorded') }}</span>
                        </li>
                    @endforeach
                    <li class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.nested_areas_hint') }}</li>
                </ul>
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

        <div class="relative overflow-hidden rounded-2xl p-5 text-white bg-gradient-to-br from-secondary via-on-secondary-container to-on-secondary-fixed-variant shadow-lg">
            <span class="absolute -top-12 -end-12 w-40 h-40 rounded-full bg-white/10"></span>
            <h2 class="relative font-semibold text-sm mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-secondary-fixed">payments</span>{{ __('parcels.value_total') }}</h2>
            @if ($value['total'] !== null)
                <p class="relative text-4xl font-bold data-tabular leading-none"><span x-data="countUp({{ (float) $value['total'] }})" x-text="display">{{ number_format($value['total']) }}</span>
                    <span class="text-sm font-normal text-secondary-fixed">{{ __('parcels.currency') }}</span></p>
                @if ($value['per_metre'] !== null)
                    <p class="relative text-sm text-secondary-fixed mt-2 data-tabular">{{ __('parcels.value_per_metre') }}: <span class="font-bold text-white">{{ number_format($value['per_metre']) }}</span></p>
                @endif
                @if (($market['average'] ?? null) !== null && $value['per_metre'] !== null)
                    @php $tdiff = round(($value['per_metre'] - $market['average']) / $market['average'] * 100, 1); @endphp
                    <div class="relative mt-5 rounded-xl bg-white/10 ring-1 ring-white/15 p-3">
                        <p class="text-[11px] text-secondary-fixed">{{ __('portal.price_market') }}: <span class="font-bold text-white data-tabular">{{ number_format($market['average']) }}</span></p>
                        <div class="relative h-2 mt-2 rounded-full bg-gradient-to-l from-error-container via-white/40 to-secondary-fixed">
                            <span class="absolute top-1/2 -translate-y-1/2 w-4 h-4 rounded-full bg-white ring-2 ring-tertiary-container" style="inset-inline-start: calc({{ max(4, min(96, 50 + $tdiff)) }}% - 8px)"></span>
                        </div>
                        <p class="text-xs font-bold mt-2">{{ abs($tdiff) < 0.5 ? __('portal.at_market') : ($tdiff > 0 ? __('portal.above_by', ['pct' => $tdiff]) : __('portal.below_by', ['pct' => abs($tdiff)])) }}</p>
                    </div>
                @endif
                @if ($value['is_derived'])
                    <p class="relative text-[11px] text-secondary-fixed mt-3">{{ __('parcels.value_derived') }}</p>
                @endif
            @else
                <p class="relative text-sm text-secondary-fixed">{{ __('parcels.not_recorded') }}</p>
            @endif
        </div>

        <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="flex items-center gap-2 font-semibold text-sm"><span class="material-symbols-outlined text-[18px] text-secondary">fingerprint</span>{{ __('portal.fingerprint_title') }}</h2>
                <span class="text-sm font-bold data-tabular">{{ $done['filled'] }}/{{ $done['total'] }}</span>
            </div>
            <x-portal.data-fingerprint :completeness="$done" class="grid grid-cols-3 sm:grid-cols-5 xl:grid-cols-7 gap-2" />
        </div>

        <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl overflow-hidden
                    border border-outline-variant dark:border-white/10 shadow-sm h-[420px] relative"
             @if ($parcelGeojson && config('services.mapbox.token'))
                 x-data="parcelMiniMap(@js($parcelGeojson), @js($neighboursGeojson), @js($parcel->parcel_no), { threeD: true, massing: @js($massing) })"
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

    {{-- ── Story ────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'story'" x-cloak class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm">
        <h2 class="flex items-center gap-2 font-semibold text-sm mb-5"><span class="material-symbols-outlined text-[18px] text-tertiary">history_edu</span>{{ __('portal.story_title') }}</h2>
        <x-portal.parcel-story :events="$story" />
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
        <div class="xl:col-span-2 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="flex items-center gap-2 font-semibold text-sm mb-3"><span class="material-symbols-outlined text-[18px] text-tertiary">signpost</span>{{ __('portal.frontage_title') }}
                @if ($frontage && ($facingText = \App\Support\ParcelFrontage::facingLabel($frontage)))
                    <span class="ms-auto text-xs font-medium px-2.5 py-1 rounded-full bg-tertiary-container/25">{{ __('portal.facing_label', ['facing' => $facingText]) }}</span>
                @endif
            </h2>
            @if ($frontage)
                <x-portal.frontage-diagram :frontage="$frontage" class="max-w-md mx-auto" />
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-6 text-center">{{ __('parcels.no_boundary') }}</p>
            @endif
        </div>

        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">{{ __('parcels.boundary_section') }}</h2>

            @if ($parcel->boundary)
                @php $b = $parcel->boundary; @endphp
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

            @if ($docs['survey']->isNotEmpty())
                <div class="mt-4 pt-3 border-t border-outline-variant dark:border-white/10">
                    @foreach ($docs['survey'] as $file)
                        <a href="{{ route('portal.documents.download', $file) }}"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface-container dark:hover:bg-white/5
                                  border border-outline-variant dark:border-white/10 mb-2 last:mb-0 transition-colors">
                            <span class="material-symbols-outlined text-[20px] text-error shrink-0">picture_as_pdf</span>
                            <span class="flex-1 min-w-0 text-sm text-on-surface dark:text-white truncate">
                                {{ $file->photo_type ? __('documents.photo_types.'.$file->photo_type->value) : '—' }}
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant dark:text-on-primary-container shrink-0">download</span>
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
                $files = $docs['deed']
                    ->sortByDesc(fn ($file) => $file->deed_id === $parcel->heldDeed?->id)
                    ->concat($docs['survey']);
            @endphp

            @forelse ($files as $file)
                @php
                    $isOldDeedScan = $file->photo_type === \App\Enums\PhotoType::Deed
                        && $file->deed_id !== null
                        && $file->deed_id !== $parcel->heldDeed?->id;
                @endphp
                <a href="{{ route('portal.documents.download', $file) }}"
                   target="_blank" rel="noopener"
                   class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-surface-container dark:hover:bg-white/5
                          border border-outline-variant dark:border-white/10 mb-2 last:mb-0 transition-colors">
                    <span class="material-symbols-outlined text-[20px] text-error shrink-0">picture_as_pdf</span>
                    <span class="flex-1 min-w-0 text-sm text-on-surface dark:text-white truncate">
                        {{ $file->photo_type ? __('documents.photo_types.'.$file->photo_type->value) : '—' }}
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
                        <div class="aspect-square rounded-xl overflow-hidden bg-surface-container dark:bg-white/5">
                            <img src="{{ $image->photo_url }}" alt="{{ $image->photo_type ? __('documents.photo_types.'.$image->photo_type->value) : '' }}"
                                 loading="lazy" class="w-full h-full object-cover" />
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.no_images') }}</p>
            @endif
        </div>
    </div>

</div>

@push('scripts')
@include('parcels.partials.mini-map-script')
@endpush

@endsection
