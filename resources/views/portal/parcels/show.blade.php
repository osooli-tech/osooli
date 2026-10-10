@extends('portal.layout')

@section('title', __('parcels.parcel_no').' '.$parcel->parcel_no)

@section('content')

@php
    $area = $twin['area'];
    $valuation = $twin['valuation'];
    $done = $twin['completeness'];
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm';
    $facing = $frontage ? \App\Support\ParcelFrontage::facingLabel($frontage) : null;
@endphp

<a href="{{ route('portal.parcels.index') }}"
   class="inline-flex items-center gap-1.5 text-sm text-on-surface-variant dark:text-on-primary-container hover:text-primary transition-colors mb-4">
    <span class="material-symbols-outlined text-[18px]">arrow_forward_ios</span>
    {{ __('parcels.back') }}
</a>

{{-- ── Parcel hero ───────────────────────────────────────────────────────── --}}
<x-portal.page-hero :title="null" id="parcel" class="mb-6">
    <div class="grid grid-cols-1 lg:grid-cols-[auto_1fr] gap-6 items-center">
        <div class="w-40 h-40 mx-auto rounded-3xl bg-white/5 ring-1 ring-white/15 flex items-center justify-center">
            <x-parcel-shape :geojson="$parcelGeojson" class="w-32 h-32 [&_path]:fill-secondary-fixed-dim/30 [&_path]:stroke-secondary-fixed" />
        </div>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-3xl font-bold data-tabular">{{ __('parcels.parcel_no') }} {{ $parcel->parcel_no }}</h1>
                @if ($frontage && $frontage['is_corner'])
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-tertiary-container text-on-tertiary-container">
                        <span class="material-symbols-outlined text-[15px]">turn_right</span>{{ __('portal.lot_corner') }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-primary-fixed-dim mt-1">
                {{ $parcel->plan?->district?->name_ar ?? '—' }} · {{ __('parcels.plan_no') }} <span class="data-tabular">{{ $parcel->plan?->plan_no ?? '—' }}</span>
                @if ($facing) · {{ __('portal.facing_label', ['facing' => $facing]) }} @endif
            </p>
            <div class="flex flex-wrap gap-2 mt-3">
                @foreach (array_filter([
                    $parcel->asset_type ? __('parcels.asset_types.'.$parcel->asset_type) : null,
                    $parcel->land_transaction ? __('parcels.land_transactions.'.$parcel->land_transaction) : null,
                ]) as $badge)
                    <span class="px-2.5 py-1 rounded-full text-xs bg-white/10 ring-1 ring-white/10">{{ $badge }}</span>
                @endforeach
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
                <x-portal.hero-stat icon="payments" :label="__('parcels.value_total')" :value="$valuation['total'] ?? '—'" :suffix="$valuation['total'] !== null ? __('parcels.currency') : ''" />
                <x-portal.hero-stat icon="square_foot" :label="__('parcels.area_deed')" :value="$area['deed'] ?? '—'" :suffix="$area['deed'] !== null ? __('dashboard.area_unit_sqm') : ''" />
                <x-portal.hero-stat icon="sell" :label="__('parcels.m_price')" :value="$valuation['per_metre'] ?? '—'" :suffix="$valuation['per_metre'] !== null ? __('parcels.currency') : ''" />
                <x-portal.hero-stat icon="donut_large" :label="__('parcels.completeness')" :value="$done['percent']" suffix="%" />
            </div>

            <div class="flex flex-wrap gap-2 mt-5">
                <a href="{{ route('portal.parcels.twin', $parcel) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold bg-tertiary-container text-on-tertiary-container hover:brightness-110 transition">
                    <span class="material-symbols-outlined text-[18px]">deployed_code</span>{{ __('parcels.twin_open') }}
                </a>
                <a href="{{ route('portal.parcels.print', $parcel) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium bg-white/10 ring-1 ring-white/20 hover:bg-white/20 transition">
                    <span class="material-symbols-outlined text-[18px]">print</span>{{ __('parcels.print_report') }}
                </a>
            </div>
        </div>
    </div>
</x-portal.page-hero>

{{-- ── Visual reading: frontage · three areas · price vs district ──────── --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
    <div class="{{ $cardCls }}">
        <h2 class="flex items-center gap-2 font-semibold text-sm mb-3">
            <span class="material-symbols-outlined text-[18px] text-tertiary">signpost</span>{{ __('portal.frontage_title') }}
        </h2>
        @if ($frontage)
            <x-portal.frontage-diagram :frontage="$frontage" class="max-w-xs mx-auto" />
            <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                    <p class="text-base font-bold data-tabular">{{ $frontage['frontage_count'] }}</p>
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.street_count') }}</p>
                </div>
                <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                    <p class="text-base font-bold data-tabular">{{ $frontage['frontage_length'] ?: '—' }}</p>
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.frontage_length') }}</p>
                </div>
                <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                    <p class="text-base font-bold data-tabular">{{ $frontage['perimeter'] ?? '—' }}</p>
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.perimeter') }}</p>
                </div>
            </div>
        @else
            <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-8 text-center">{{ __('parcels.no_boundary') }}</p>
        @endif
    </div>

    <div class="{{ $cardCls }}">
        <h2 class="flex items-center gap-2 font-semibold text-sm mb-1">
            <span class="material-symbols-outlined text-[18px] text-secondary">stacked_bar_chart</span>{{ __('parcels.area_section') }}
        </h2>
        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-5">{{ __('portal.three_areas_hint') }}</p>
        @php $areaMax = max(array_filter([$area['deed'], $area['measured'], $area['computed']]) ?: [1]); @endphp
        <div class="space-y-4">
            @foreach ([
                ['label' => __('parcels.area_deed_label'), 'value' => $area['deed'], 'tone' => 'from-primary to-surface-tint'],
                ['label' => __('parcels.area_measured_label'), 'value' => $area['measured'], 'tone' => 'from-tertiary to-tertiary-container'],
                ['label' => __('parcels.area_computed_label'), 'value' => $area['computed'], 'tone' => 'from-secondary to-secondary-fixed-dim'],
            ] as $a)
                <div>
                    <div class="flex justify-between text-sm">
                        <span>{{ $a['label'] }}</span>
                        <span class="font-bold data-tabular">{{ $a['value'] !== null ? number_format($a['value']).' '.__('dashboard.area_unit_sqm') : __('parcels.not_recorded') }}</span>
                    </div>
                    <div class="mt-1.5 h-3 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                        @if ($a['value'] !== null)
                            <div class="h-full rounded-full bg-gradient-to-l {{ $a['tone'] }}" style="width: {{ round($a['value'] / $areaMax * 100) }}%"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="{{ $cardCls }}">
        <h2 class="flex items-center gap-2 font-semibold text-sm mb-1">
            <span class="material-symbols-outlined text-[18px] text-secondary">query_stats</span>{{ __('portal.price_compare_title') }}
        </h2>
        @if ($valuation['per_metre'] !== null && ($market['average'] ?? null) !== null)
            @php
                $diff = round(($valuation['per_metre'] - $market['average']) / $market['average'] * 100, 1);
                $pos = max(4, min(96, 50 + $diff));
            @endphp
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-6">{{ __('portal.price_compare_basis', ['count' => $market['comparables']]) }}</p>
            {{-- Gauge: centre is the district average, the marker is this parcel --}}
            <div class="relative h-4 rounded-full bg-gradient-to-l from-error/70 via-surface-container-high to-secondary">
                <span class="absolute top-1/2 start-1/2 -translate-y-1/2 w-0.5 h-7 bg-on-surface/40 dark:bg-white/50"></span>
                <span class="absolute top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-white ring-4 ring-primary shadow-md" style="inset-inline-start: calc({{ $pos }}% - 12px)"></span>
            </div>
            <div class="flex justify-between text-[11px] text-on-surface-variant dark:text-on-primary-container mt-2">
                <span>{{ __('portal.below_market') }}</span><span>{{ __('portal.price_market') }}</span><span>{{ __('portal.above_market') }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-5">
                <div class="rounded-xl bg-secondary/10 p-3">
                    <p class="text-[11px] text-secondary">{{ __('portal.price_mine') }}</p>
                    <p class="text-lg font-bold data-tabular">{{ number_format($valuation['per_metre']) }} <span class="text-xs font-normal">{{ __('parcels.currency') }}</span></p>
                </div>
                <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-3">
                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.price_market') }}</p>
                    <p class="text-lg font-bold data-tabular">{{ number_format($market['average']) }} <span class="text-xs font-normal">{{ __('parcels.currency') }}</span></p>
                </div>
            </div>
            <p class="mt-3 text-sm font-bold {{ $diff >= 0 ? 'text-secondary' : 'text-error' }}">
                {{ abs($diff) < 0.5 ? __('portal.at_market') : ($diff > 0 ? __('portal.above_by', ['pct' => $diff]) : __('portal.below_by', ['pct' => abs($diff)])) }}
            </p>
        @else
            <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-8 text-center">
                {{ $valuation['per_metre'] === null ? __('portal.alert_unpriced') : __('portal.price_compare_too_few') }}
            </p>
        @endif
    </div>
</div>

{{-- ── The parcel's story · its data fingerprint · who owns it ───────────── --}}
@php
    $currentDeed = $parcel->deeds->firstWhere('id', $parcel->heldDeed?->id) ?? $parcel->deeds->first();
    $holders = $currentDeed?->owners ?? collect();
    $shareColors = ['#006c4e', '#c9a84c', '#002444', '#68dbae', '#436084', '#755b00'];
    $recordedShares = $holders->sum(fn ($o) => (float) $o->pivot->ownership_share);
@endphp
<div class="grid grid-cols-1 xl:grid-cols-12 gap-5 mb-5">
    <div class="xl:col-span-7 {{ $cardCls }}">
        <h2 class="flex items-center gap-2 font-semibold text-sm mb-5">
            <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-tertiary to-tertiary-container text-white flex items-center justify-center"><span class="material-symbols-outlined text-[18px]">history_edu</span></span>
            {{ __('portal.story_title') }}
        </h2>
        <x-portal.parcel-story :events="$story" />
    </div>

    <div class="xl:col-span-5 space-y-5">
        <div class="{{ $cardCls }}">
            <div class="flex items-center justify-between mb-4">
                <h2 class="flex items-center gap-2 font-semibold text-sm">
                    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-secondary to-on-secondary-fixed-variant text-white flex items-center justify-center"><span class="material-symbols-outlined text-[18px]">fingerprint</span></span>
                    {{ __('portal.fingerprint_title') }}
                </h2>
                <span class="text-sm font-bold data-tabular">{{ $done['filled'] }}/{{ $done['total'] }}</span>
            </div>
            <x-portal.data-fingerprint :completeness="$done" />
        </div>

        <div class="{{ $cardCls }}">
            <h2 class="flex items-center gap-2 font-semibold text-sm mb-4">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-primary to-surface-tint text-white flex items-center justify-center"><span class="material-symbols-outlined text-[18px]">pie_chart</span></span>
                {{ __('portal.ownership_title') }}
            </h2>
            @if ($parcel->relationLoaded('previousHolders') && $parcel->previousHolders->isNotEmpty())
                {{-- Where the parcel came from: the earlier holder's name, and nothing else of theirs --}}
                <p class="flex items-center gap-1.5 text-xs text-on-surface-variant dark:text-on-primary-container -mt-2 mb-4">
                    <span class="material-symbols-outlined text-[16px]">history</span>
                    {{ __('portal.acquired_from', ['name' => $parcel->previousHolders->pluck('name')->implode('، ')]) }}
                </p>
            @endif
            @if ($holders->isEmpty())
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.not_recorded') }}</p>
            @else
                {{-- One bar split by share; owners without a recorded share split the remainder equally --}}
                @php
                    $unrecorded = $holders->filter(fn ($o) => ! $o->pivot->ownership_share)->count();
                    $remainder = max(0, 100 - $recordedShares);
                @endphp
                <div class="flex h-5 rounded-full overflow-hidden ring-1 ring-outline-variant/50 dark:ring-white/10">
                    @foreach ($holders as $i => $holder)
                        @php $w = $holder->pivot->ownership_share ? (float) $holder->pivot->ownership_share : ($unrecorded ? $remainder / $unrecorded : 0); @endphp
                        <span class="h-full" style="width: {{ $w }}%; background: {{ $shareColors[$i % count($shareColors)] }}" title="{{ $holder->name }}"></span>
                    @endforeach
                </div>
                <ul class="mt-4 space-y-2">
                    @foreach ($holders as $i => $holder)
                        <li class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="w-7 h-7 rounded-lg text-white text-xs font-bold flex items-center justify-center shrink-0" style="background: {{ $shareColors[$i % count($shareColors)] }}">{{ mb_substr($holder->name, 0, 1) }}</span>
                                <span class="truncate">{{ $holder->name }}</span>
                            </span>
                            <span class="font-bold data-tabular shrink-0">{{ $holder->pivot->ownership_share ? rtrim(rtrim((string) $holder->pivot->ownership_share, '0'), '.').'%' : __('parcels.share_unrecorded') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Main column (deeds + survey decisions) ─────────────────────── --}}
    <div class="xl:col-span-2 space-y-5">

        {{-- Deeds --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                    border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-4
                        border-b border-outline-variant dark:border-white/10">
                <span class="material-symbols-outlined text-[18px] text-secondary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    description
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.deeds_section') }}
                </h2>
                <div class="ms-auto flex items-center gap-2">
                    @if ($parcel->fall_in)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                     bg-secondary-container/40 text-on-surface dark:text-white/80"
                              title="{{ __('parcels.ownership_basis') }}">
                            {{ $parcel->fall_in }}
                        </span>
                    @endif
                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ $parcel->deeds->count() }}
                    </span>
                </div>
            </div>

            @if ($parcel->deeds->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[40px] opacity-30">description</span>
                    <p class="text-sm">{{ __('parcels.no_deeds') }}</p>
                </div>
            @else
                <div class="divide-y divide-outline-variant dark:divide-white/10">
                    @foreach ($parcel->deeds as $deed)
                        @php
                            $isUpdated = $deed->deed_status === \App\Enums\DeedStatus::Updated->value;
                        @endphp
                        <div class="p-5">
                            <div class="flex flex-wrap items-center gap-3 mb-4">
                                <span class="font-bold text-on-surface dark:text-white data-tabular text-base">
                                    {{ $deed->deed_no ?? '—' }}
                                </span>
                                @if ($deed->deed_status)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                 {{ $isUpdated
                                                     ? 'bg-secondary/10 text-secondary'
                                                     : 'bg-error/10 text-error' }}">
                                        {{ __('parcels.deed_statuses.'.$deed->deed_status) }}
                                    </span>
                                @endif
                                @if ($deed->deed_class)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                 bg-tertiary-container/30 text-on-surface dark:text-white/80">
                                        {{ $deed->deed_class }}
                                    </span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-4">
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.deed_date') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white data-tabular">
                                        {{ $deed->deed_date_hijri ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.area_deed') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white data-tabular">
                                        @if ($deed->deed_area)
                                            {{ number_format((float) $deed->deed_area, 0) }}
                                            {{ __('dashboard.area_unit_sqm') }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.deed_class') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white">
                                        {{ $deed->deed_class ?? '—' }}
                                    </dd>
                                </div>
                            </dl>

                            @if ($deed->owners->isNotEmpty())
                                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-3">
                                    <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container mb-2">
                                        {{ __('parcels.owners') }}
                                    </p>
                                    <div class="space-y-2">
                                        @foreach ($deed->owners as $deedOwner)
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant"
                                                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                                                        person
                                                    </span>
                                                    <span class="text-on-surface dark:text-white font-medium">
                                                        {{ $deedOwner->name }}
                                                    </span>
                                                </div>
                                                @if ($deedOwner->pivot->ownership_share)
                                                    <span class="text-xs font-medium text-secondary shrink-0 data-tabular">
                                                        {{ $deedOwner->pivot->ownership_share }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Survey Decisions --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                    border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-4
                        border-b border-outline-variant dark:border-white/10">
                <span class="material-symbols-outlined text-[18px] text-tertiary-container"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    gavel
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.survey_decisions_section') }}
                </h2>
                <span class="ms-auto text-xs text-on-surface-variant dark:text-on-primary-container">
                    {{ $parcel->surveyDecisions->count() }}
                </span>
            </div>

            @if ($parcel->surveyDecisions->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[36px] opacity-30">gavel</span>
                    <p class="text-sm">{{ __('parcels.no_decisions') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline-variant dark:border-white/10
                                        bg-surface-container dark:bg-[#1e2435]">
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.folder') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.report_no') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.qrar_no') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.qrar_source') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                            @foreach ($parcel->surveyDecisions as $decision)
                                <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                        {{ $decision->folder ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                        {{ $decision->report_no ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                        {{ $decision->qrar_no ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                        {{ $decision->qrar_source ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    {{-- ── Side column (parcel info + photos + documents) ─────────────── --}}
    <div class="space-y-5">

        {{-- Parcel info --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-primary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    terrain
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.parcel_info') }}
                </h2>
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.parcel_no') }}
                    </dt>
                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end">
                        {{ $parcel->parcel_no ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.geo_id') }}
                    </dt>
                    <dd class="font-medium text-on-surface dark:text-white data-tabular text-end text-xs break-all">
                        {{ $parcel->geo_id ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.plan_no') }}
                    </dt>
                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end">
                        {{ $parcel->plan?->plan_no ?? '—' }}
                    </dd>
                </div>
                @if ($parcel->plan?->district)
                    <div class="flex justify-between gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                            {{ __('parcels.district') }}
                        </dt>
                        <dd class="font-medium text-on-surface dark:text-white text-end">
                            {{ app()->isLocale('ar') ? $parcel->plan->district->name_ar : $parcel->plan->district->name_en }}
                        </dd>
                    </div>
                @endif
                @if ($parcel->parent)
                    {{-- Plain text, not a link — a parent parcel is not guaranteed
                         to be one of this owner's own parcels. --}}
                    <div class="flex justify-between gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                            {{ __('parcels.parent_parcel') }}
                        </dt>
                        <dd class="font-medium text-on-surface dark:text-white data-tabular text-end">
                            {{ $parcel->parent->parcel_no ?: $parcel->parent->geo_id }}
                        </dd>
                    </div>
                @endif
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.allocation_method') }}
                    </dt>
                    <dd class="font-medium text-on-surface dark:text-white text-end">
                        {{ $parcel->allocation_method ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.m_price') }}
                    </dt>
                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end">
                        {{ $parcel->m_price === null ? '—' : number_format((float) $parcel->m_price).' '.__('parcels.sar') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.parcel_price') }}
                    </dt>
                    <dd class="font-semibold text-secondary data-tabular text-end">
                        {{ $parcel->parcel_price === null ? '—' : number_format((float) $parcel->parcel_price).' '.__('parcels.sar') }}
                    </dd>
                </div>
            </dl>
        </div>

        @php
            $isGalleryImage = fn ($photo) => $photo->isGalleryImage();
            $images = $parcel->photos->filter($isGalleryImage);
            $documents = $parcel->photos->reject($isGalleryImage);
        @endphp

        {{-- Photos --}}
        @if ($images->isNotEmpty())
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                        border border-outline-variant dark:border-white/10 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-tertiary-container"
                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                        photo_library
                    </span>
                    <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                        {{ __('parcels.photos_section') }}
                    </h2>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($images as $photo)
                        <div class="aspect-square rounded-xl overflow-hidden bg-surface-container dark:bg-white/5">
                            <img src="{{ route('portal.documents.preview', $photo) }}"
                                 alt="{{ __('parcels.photos_section') }}"
                                 class="w-full h-full object-cover" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Documents (deed scans, boundary survey cards) — download only,
             through the portal's own owner-scoped, audited download route. --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-tertiary-container"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    description
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.documents_section') }}
                </h2>
            </div>

            @if ($documents->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($documents as $doc)
                        <a href="{{ route('portal.documents.download', $doc) }}"
                           target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant dark:border-white/10
                                  hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <span class="material-symbols-outlined text-[20px] text-secondary shrink-0">
                                {{ str_starts_with((string) $doc->mime_type, 'image/') ? 'image' : 'picture_as_pdf' }}
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-on-surface dark:text-white truncate">
                                    {{ $doc->photo_type ? __('documents.photo_types.'.$doc->photo_type->value) : '—' }}
                                </span>
                                @if ($doc->original_name)
                                    <span class="block text-xs text-on-surface-variant dark:text-on-primary-container truncate" dir="auto">
                                        {{ $doc->original_name }}
                                    </span>
                                @endif
                            </span>
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant dark:text-on-primary-container shrink-0">download</span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-2">
                    {{ __('parcels.no_documents') }}
                </p>
            @endif
        </div>

    </div>

</div>

{{-- ── Data-change requests ───────────────────────────────────────────── --}}
@php
    $requestOptions = [
        'asset_type' => __('parcels.asset_types'),
        'land_transaction' => __('parcels.land_transactions'),
        'deed_status' => __('parcels.deed_statuses'),
    ];
@endphp
<div class="mt-5 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
            border border-outline-variant dark:border-white/10 shadow-sm"
     x-data="{ field: @js(old('field_name', $editableFields[0])), options: @js($requestOptions) }">
    <div class="flex items-center gap-2 mb-1">
        <span class="material-symbols-outlined text-[18px] text-primary">edit_note</span>
        <h2 class="font-semibold text-on-surface dark:text-white text-sm">{{ __('portal.request_edit') }}</h2>
    </div>
    <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-4">{{ __('portal.request_edit_hint') }}</p>

    @if (session('status'))
        <p class="mb-3 text-sm text-secondary font-medium">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-3 text-sm text-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('portal.parcels.modification-requests.store', $parcel) }}"
          class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-xs font-medium mb-1 text-on-surface-variant dark:text-on-primary-container">{{ __('portal.request_field') }}</label>
            <select name="field_name" x-model="field"
                    class="w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10">
                @foreach ($editableFields as $field)
                    <option value="{{ $field }}">{{ __('parcels.'.$field) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1 text-on-surface-variant dark:text-on-primary-container">{{ __('portal.request_new_value') }}</label>
            <template x-if="options[field]">
                <select name="new_value" required
                        class="w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10">
                    <template x-for="(label, value) in options[field]" :key="value">
                        <option :value="value" x-text="label"></option>
                    </template>
                </select>
            </template>
            <template x-if="! options[field]">
                <input type="text" name="new_value" required maxlength="255" value="{{ old('new_value') }}"
                       class="w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10">
            </template>
        </div>
        <div>
            <label class="block text-xs font-medium mb-1 text-on-surface-variant dark:text-on-primary-container">{{ __('portal.request_notes') }}</label>
            <input type="text" name="notes" maxlength="1000" value="{{ old('notes') }}"
                   class="w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10">
        </div>
        <button type="submit"
                class="px-4 py-2 rounded-xl text-sm font-medium bg-primary text-white hover:opacity-90 transition-opacity">
            {{ __('portal.request_submit') }}
        </button>
    </form>

    <div class="mt-5 pt-4 border-t border-outline-variant dark:border-white/10">
        <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container mb-2">{{ __('portal.request_history') }}</p>
        @forelse ($modificationRequests as $req)
            <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm border-b border-outline-variant dark:border-white/10 last:border-0">
                <span class="font-medium">{{ $req->fieldLabel() }}</span>
                <span class="text-on-surface-variant dark:text-on-primary-container">{{ $req->old_value ?? '—' }} ← {{ $req->new_value }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-tertiary-container/30">{{ __('modification_requests.status.'.$req->status->value) }}</span>
            </div>
        @empty
            <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('portal.request_none') }}</p>
        @endforelse
    </div>
</div>

{{-- ── Boundaries + Mini-Map (full width, side by side) ────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mt-5">

    {{-- Compass card --}}
    <div class="xl:col-span-2 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-[18px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                straighten
            </span>
            <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                {{ __('parcels.boundary_section') }}
            </h2>
        </div>

        @if (! $parcel->boundary)
            <div class="flex flex-col items-center justify-center py-8 gap-3
                        text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[36px] opacity-30">straighten</span>
                <p class="text-sm">{{ __('parcels.no_boundary') }}</p>
            </div>
        @else
            @php $b = $parcel->boundary; @endphp

            <div class="grid grid-cols-3 gap-2 text-center text-xs mb-4" dir="ltr">
                <div></div>
                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                    <p class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ __('parcels.n_border') }}</p>
                    <p class="font-semibold text-on-surface dark:text-white leading-snug">{{ $b->n_border ?? '—' }}</p>
                    @if ($b->n_dim) <p class="text-secondary data-tabular mt-0.5">{{ $b->n_dim }} م</p> @endif
                </div>
                <div></div>

                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                    <p class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ __('parcels.w_border') }}</p>
                    <p class="font-semibold text-on-surface dark:text-white leading-snug">{{ $b->w_border ?? '—' }}</p>
                    @if ($b->w_dim) <p class="text-secondary data-tabular mt-0.5">{{ $b->w_dim }} م</p> @endif
                </div>

                <div class="flex flex-col items-center justify-center gap-1">
                    <svg width="28" height="28" viewBox="0 0 28 28" class="shrink-0">
                        <polygon points="14,2 18,16 14,12 10,16" fill="#006c4e" opacity="0.9"/>
                        <polygon points="14,26 18,12 14,16 10,12" fill="#9e9e9e" opacity="0.45"/>
                    </svg>
                    <span class="text-[10px] font-bold text-secondary">ش</span>
                </div>

                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                    <p class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ __('parcels.e_border') }}</p>
                    <p class="font-semibold text-on-surface dark:text-white leading-snug">{{ $b->e_border ?? '—' }}</p>
                    @if ($b->e_dim) <p class="text-secondary data-tabular mt-0.5">{{ $b->e_dim }} م</p> @endif
                </div>

                <div></div>
                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                    <p class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ __('parcels.s_border') }}</p>
                    <p class="font-semibold text-on-surface dark:text-white leading-snug">{{ $b->s_border ?? '—' }}</p>
                    @if ($b->s_dim) <p class="text-secondary data-tabular mt-0.5">{{ $b->s_dim }} م</p> @endif
                </div>
                <div></div>
            </div>

            <dl class="space-y-2 text-sm border-t border-outline-variant dark:border-white/10 pt-3">
                @if ($b->measured_area)
                    <div class="flex justify-between gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.measured_area') }}</dt>
                        <dd class="font-semibold text-on-surface dark:text-white data-tabular">
                            {{ number_format((float) $b->measured_area, 0) }} {{ __('dashboard.area_unit_sqm') }}
                        </dd>
                    </div>
                @endif
                @if ($b->engineeringOffice)
                    <div class="flex justify-between gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.engineering_office') }}</dt>
                        <dd class="font-medium text-on-surface dark:text-white text-end">{{ $b->engineeringOffice->name }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>

    {{-- Mini-map --}}
    <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl overflow-hidden
                border border-outline-variant dark:border-white/10 shadow-sm min-h-[440px] relative"
         x-data="parcelMiniMap(@js($parcelGeojson), @js($neighboursGeojson), @js($parcel->parcel_no), { threeD: true, massing: @js($massing) })">
        @if ($parcelGeojson && config('services.mapbox.token'))
            <div id="parcel-mini-map" class="absolute inset-0 w-full h-full rounded-2xl"></div>
        @elseif (! $parcelGeojson)
            <div class="flex flex-col items-center justify-center h-full gap-3 py-12 px-6 text-center
                        text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[40px] opacity-30">pentagon</span>
                <p class="text-sm">{{ __('parcels.geometry_missing') }}</p>
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-full gap-3 py-12
                        text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[40px] opacity-30">map</span>
                <p class="text-sm">{{ __('dashboard.mapbox_missing') }}</p>
            </div>
        @endif
    </div>

</div>

@push('scripts')
@include('parcels.partials.mini-map-script')
@endpush

@endsection
