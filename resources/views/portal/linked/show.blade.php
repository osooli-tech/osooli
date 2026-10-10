@extends('portal.layout')

@section('title', __('parcels.parcel_no').' '.$parcel->parcel_no)

@section('content')
@php
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm border border-outline-variant dark:border-white/10';
    $place = collect([$parcel->plan?->district?->name_ar, $parcel->plan?->district?->city?->name_ar])->filter()->implode(' · ');
@endphp
<div class="space-y-5">
    <x-portal.page-hero icon="family_restroom"
                        :title="__('parcels.parcel_no').' '.($parcel->parcel_no ?? '—')"
                        :subtitle="__('portal.linked_page_subtitle', ['holder' => implode('، ', $holders) ?: __('portal.linked_no_holder')])"
                        id="linked-parcel">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
            <x-portal.hero-stat icon="grid_on" :label="__('parcels.plan_no')" :value="$parcel->plan?->plan_no ?? '—'" />
            <x-portal.hero-stat icon="square_foot" :label="__('parcels.area_deed')" :value="$area ?? '—'" :suffix="$area !== null ? __('dashboard.area_unit_sqm') : ''" />
            <x-portal.hero-stat icon="category" :label="__('parcels.asset_type')" :value="$parcel->asset_type ?? '—'" />
            @if ($showValue)
                <x-portal.hero-stat icon="payments" :label="__('parcels.parcel_price')" :value="$value ?? '—'" :suffix="$value !== null ? __('parcels.currency') : ''" />
            @endif
        </div>
    </x-portal.page-hero>

    {{-- A small hint, not a warning: whose portfolio this parcel sits in --}}
    <p class="flex items-center gap-1.5 text-xs text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[16px]">info</span>
        {{ __('portal.linked_hint') }}@if ($place) · {{ $place }}@endif
    </p>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        <div class="xl:col-span-2 space-y-5">
            @if ($frontage !== null)
                <section class="{{ $cardCls }}">
                    <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                        <span class="material-symbols-outlined text-[18px] text-secondary">straighten</span>{{ __('portal.frontage_title') }}
                    </h2>
                    <x-portal.frontage-diagram :frontage="$frontage" />
                </section>
            @endif

            @if ($deed !== null)
                <section class="{{ $cardCls }}">
                    <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                        <span class="material-symbols-outlined text-[18px] text-tertiary">workspace_premium</span>{{ __('portal.linked_deed') }}
                    </h2>
                    <dl class="space-y-2.5 text-sm">
                        @foreach ([
                            'parcels.deed_no' => $deed->deed_no,
                            'parcels.deed_date' => $deed->deed_date_hijri,
                            'parcels.deed_status' => $deed->deed_status,
                            'parcels.deed_class' => $deed->deed_class,
                        ] as $label => $shown)
                            <div class="flex justify-between gap-3">
                                <dt class="text-on-surface-variant dark:text-on-primary-container">{{ __($label) }}</dt>
                                <dd class="font-semibold data-tabular text-end">{{ $shown ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if ($documents->isNotEmpty())
                <section class="{{ $cardCls }}">
                    <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                        <span class="material-symbols-outlined text-[18px] text-primary dark:text-primary-fixed-dim">folder_open</span>{{ __('portal.nav_documents') }}
                    </h2>
                    <ul class="space-y-2">
                        @foreach ($documents as $document)
                            <li>
                                <a href="{{ route('portal.documents.download', $document) }}"
                                   class="flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 border border-outline-variant dark:border-white/10 hover:bg-surface-container dark:hover:bg-white/5 text-sm">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <span class="material-symbols-outlined text-[18px] shrink-0">description</span>
                                        <span class="truncate">{{ $document->photo_type ? __('documents.photo_types.'.$document->photo_type->value) : '—' }}</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-secondary shrink-0">download</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <div class="xl:col-span-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl overflow-hidden
                    border border-outline-variant dark:border-white/10 shadow-sm min-h-[440px] relative"
             @if ($parcelGeojson && config('services.mapbox.token'))
                 x-data="parcelMiniMap(@js($parcelGeojson), null, @js($parcel->parcel_no), { threeD: true, massing: @js($massing) })"
             @endif>
            @if ($parcelGeojson && config('services.mapbox.token'))
                <div id="parcel-mini-map" class="absolute inset-0 w-full h-full rounded-2xl"></div>
            @else
                <div class="flex flex-col items-center justify-center h-full gap-3 py-12 text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[48px] opacity-40">map</span>
                    <p class="text-sm">{{ __('portal.linked_no_map') }}</p>
                </div>
            @endif
        </div>
    </div>

    <a href="{{ route('portal.linked.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-secondary hover:underline">
        <span class="material-symbols-outlined text-[18px] ltr:rotate-180">arrow_forward</span>{{ __('portal.linked_back') }}
    </a>
</div>
@endsection

@push('scripts')
@include('parcels.partials.mini-map-script')
@endpush
