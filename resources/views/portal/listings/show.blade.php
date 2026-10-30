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
            {{-- Contact goes through Sukooki: neither side sees the other's number --}}
            <section class="{{ $cardCls }}">
                <h2 class="flex items-center gap-2 text-sm font-semibold mb-2">
                    <span class="material-symbols-outlined text-[18px] text-secondary">handshake</span>{{ __('listings.interest_title') }}
                </h2>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-4">{{ __('listings.interest_hint') }}</p>

                @if (session('listing_status'))
                    <p class="mb-3 text-sm text-secondary font-medium">{{ session('listing_status') }}</p>
                @endif

                @if ($listing['is_mine'])
                    <p class="flex items-center gap-2 text-sm font-medium rounded-xl p-3 bg-secondary/10 text-secondary">
                        <span class="material-symbols-outlined text-[18px]">storefront</span>{{ __('listings.yours_hint') }}
                    </p>
                @elseif ($listing['my_interest'] !== null && $listing['my_interest'] !== \App\Enums\InquiryStatus::Closed->value)
                    <p class="flex items-center gap-2 text-sm font-medium rounded-xl p-3 bg-secondary/10 text-secondary">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        {{ __('listings.interest_status', ['status' => \App\Enums\InquiryStatus::from($listing['my_interest'])->label()]) }}
                    </p>
                @else
                    <form method="POST" action="{{ route('portal.listings.interest', $listing['id']) }}" class="space-y-3">
                        @csrf
                        <textarea name="note" rows="2" maxlength="500" placeholder="{{ __('listings.interest_note') }}"
                                  class="w-full px-3 py-2 text-sm rounded-xl resize-none bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10">{{ old('note') }}</textarea>
                        @error('note') <p class="text-xs text-error">{{ $message }}</p> @enderror
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium bg-secondary text-white hover:opacity-90 transition-opacity">
                            <span class="material-symbols-outlined text-[18px]">front_hand</span>{{ __('listings.interest_submit') }}
                        </button>
                    </form>
                @endif

                <p class="flex items-center gap-1.5 mt-4 text-xs text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[16px]">visibility_off</span>{{ __('listings.privacy_note') }}
                </p>
            </section>

            {{-- The basics at a glance --}}
            <section class="{{ $cardCls }}">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="col-span-2">
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.streets') }}</dt>
                        <dd class="font-medium">{{ collect($listing['streets'])->map(fn ($s) => $s['side'].($s['width'] ? ' '.rtrim(rtrim(number_format($s['width'], 1), '0'), '.').' '.__('portal.metre_short') : ''))->implode('، ') ?: __('listings.no_streets') }}</dd>
                    </div>
                    @if ($listing['frontage'] !== null && $listing['frontage']['frontage_length'] > 0)
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.frontage_length') }}</dt>
                            <dd class="font-bold data-tabular">{{ number_format($listing['frontage']['frontage_length'], 2) }}</dd>
                        </div>
                    @endif
                    @if ($listing['frontage'] !== null && $listing['frontage']['perimeter'] !== null)
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.perimeter') }}</dt>
                            <dd class="font-bold data-tabular">{{ number_format($listing['frontage']['perimeter'], 2) }}</dd>
                        </div>
                    @endif
                </dl>
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
