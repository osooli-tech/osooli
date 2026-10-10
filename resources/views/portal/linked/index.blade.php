@extends('portal.layout')

@section('title', __('portal.linked_title'))

@section('content')
@php
    $totalParcels = array_sum(array_column($groups, 'parcels_count'));
    $totalArea = array_sum(array_column($groups, 'area'));
@endphp
<div class="space-y-5">
    <x-portal.page-hero icon="family_restroom" :title="__('portal.linked_title')" :subtitle="__('portal.linked_subtitle')" id="linked">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-6">
            <x-portal.hero-stat icon="group" :label="__('portal.linked_holders')" :value="count($groups)" />
            <x-portal.hero-stat icon="map" :label="__('dashboard.total_parcels')" :value="$totalParcels" />
            <x-portal.hero-stat icon="square_foot" :label="__('dashboard.total_area')" :value="$totalArea" :suffix="__('dashboard.area_unit_sqm')" />
        </div>
    </x-portal.page-hero>

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
                            <span class="material-symbols-outlined text-[14px]">info</span>{{ __('portal.linked_hint') }}
                        </p>
                    </div>
                </div>
                <dl class="flex items-center gap-5 text-sm">
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
                    <a href="{{ route('portal.linked.show', $row['id']) }}"
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
