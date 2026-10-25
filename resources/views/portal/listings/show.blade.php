@extends('portal.layout')

@section('title', __('listings.title'))

@section('content')
@php
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm border border-outline-variant dark:border-white/10';
    $place = collect([$listing['district'], $listing['city']])->filter()->implode(' · ');
    $hasMap = $listing['geojson'] && config('services.mapbox.token');
@endphp
<div class="space-y-5">
    <x-portal.page-hero icon="storefront" :title="__('listings.title')" :subtitle="$place ?: null" id="listing">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mt-6">
            <x-portal.hero-stat icon="square_foot" :label="__('listings.area')" :value="$listing['area'] ?? '—'" :suffix="$listing['area'] !== null ? __('dashboard.area_unit_sqm') : ''" />
            <x-portal.hero-stat icon="category" :label="__('listings.asset_type')" :value="$listing['asset_type'] ?? '—'" />
            <x-portal.hero-stat icon="event" :label="__('listings.listed_at')" :value="$listing['listed_at'] ?? '—'" />
        </div>
    </x-portal.page-hero>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        <div class="xl:col-span-2 space-y-5">
            {{-- Contact: the only way from a listing to its seller --}}
            <section class="{{ $cardCls }}">
                <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                    <span class="material-symbols-outlined text-[18px] text-secondary">call</span>{{ __('listings.contact_title') }}
                </h2>
                <p class="text-2xl font-bold data-tabular ltr text-center select-all" dir="ltr">{{ $listing['contact_phone'] }}</p>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <a href="tel:{{ $listing['contact_phone'] }}"
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium bg-primary text-white hover:opacity-90 transition-opacity">
                        <span class="material-symbols-outlined text-[18px]">call</span>{{ __('listings.call') }}
                    </a>
                    <a href="https://wa.me/{{ $listing['whatsapp'] }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium bg-secondary text-white hover:opacity-90 transition-opacity">
                        <span class="material-symbols-outlined text-[18px]">chat</span>{{ __('listings.whatsapp') }}
                    </a>
                </div>
                <p class="flex items-center gap-1.5 mt-4 text-xs text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[16px]">visibility_off</span>{{ __('listings.privacy_note') }}
                </p>
            </section>

            <section class="{{ $cardCls }}">
                <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                    <span class="material-symbols-outlined text-[18px] text-tertiary">straighten</span>{{ __('listings.shape_title') }}
                </h2>
                @if ($listing['frontage'] !== null)
                    <x-portal.frontage-diagram :frontage="$listing['frontage']" :geojson="$listing['geojson']" class="max-w-sm mx-auto" />
                @else
                    <div class="flex justify-center py-4">
                        <x-parcel-shape :geojson="$listing['geojson']" class="w-40 h-40 [&_path]:fill-secondary-fixed-dim/30 [&_path]:stroke-secondary" />
                    </div>
                @endif
            </section>
        </div>

        <div class="xl:col-span-3 {{ $cardCls }} !p-0 overflow-hidden min-h-[440px] relative"
             @if ($hasMap) x-data="parcelMiniMap(@js($listing['geojson']), null, '', {})" @endif>
            @if ($hasMap)
                <div id="parcel-mini-map" class="absolute inset-0 w-full h-full rounded-2xl"></div>
            @else
                <div class="flex flex-col items-center justify-center h-full gap-3 py-12 text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[48px] opacity-40">map</span>
                    <p class="text-sm">{{ __('listings.no_map') }}</p>
                </div>
            @endif
        </div>
    </div>

    <a href="{{ route('portal.listings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-secondary hover:underline">
        <span class="material-symbols-outlined text-[18px] ltr:rotate-180">arrow_forward</span>{{ __('listings.back') }}
    </a>
</div>
@endsection

@push('scripts')
@include('parcels.partials.mini-map-script')
@endpush
