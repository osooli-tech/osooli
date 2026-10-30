@extends('portal.layout')

@section('title', __('listings.title'))

@section('content')
<div class="space-y-5">
    <x-portal.page-hero icon="storefront" :title="__('listings.title')" :subtitle="__('listings.subtitle')" id="market">
        <p class="mt-5 inline-flex items-center gap-1.5 text-xs text-primary-fixed-dim bg-white/10 ring-1 ring-white/15 rounded-full px-3 py-1.5">
            <span class="material-symbols-outlined text-[16px]">visibility_off</span>{{ __('listings.privacy_note') }}
        </p>
    </x-portal.page-hero>

    @if (session('listing_status'))
        <p class="text-sm text-secondary font-medium">{{ session('listing_status') }}</p>
    @endif

    {{-- The owner's own listings and where each stands --}}
    @if ($mine->isNotEmpty())
        <section class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm">
            <h2 class="flex items-center gap-2 text-sm font-semibold mb-3">
                <span class="material-symbols-outlined text-[18px] text-tertiary">sell</span>{{ __('listings.mine_title') }}
            </h2>
            <ul class="divide-y divide-outline-variant/50 dark:divide-white/5">
                @foreach ($mine as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('portal.parcels.show', $item->parcel_id) }}" class="font-medium hover:underline">
                                {{ __('listings.mine_parcel', ['no' => $item->parcel?->parcel_no ?? '—']) }}
                            </a>
                            <span class="ms-2 text-[11px] px-2 py-0.5 rounded-full
                                         {{ $item->status === \App\Enums\ListingStatus::Approved ? 'bg-secondary/15 text-secondary' : 'bg-surface-container dark:bg-white/5 text-on-surface-variant dark:text-on-primary-container' }}">
                                {{ $item->status->label() }}
                            </span>
                            @if ($item->status === \App\Enums\ListingStatus::Rejected && $item->rejection_reason)
                                <p class="text-xs text-error mt-1">{{ __('listings.rejection_reason', ['reason' => $item->rejection_reason]) }}</p>
                            @endif
                        </div>
                        @if ($item->status->isOpen())
                            <form method="POST" action="{{ route('portal.listings.destroy', $item->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-error hover:underline">{{ __('listings.withdraw') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Every listing on one map; a click opens its area, streets and place --}}
    @if ($map['features'] !== [] && config('services.mapbox.token'))
        <section class="relative h-[420px] rounded-2xl overflow-hidden border border-outline-variant dark:border-white/10 shadow-sm"
                 x-data="marketMap(@js($map), @js(route('portal.listings.show', '__ID__')), @js([
                     'area' => __('listings.area'),
                     'streets' => __('listings.streets'),
                     'type' => __('listings.asset_type'),
                     'place' => __('listings.place'),
                     'view' => __('listings.view'),
                     'none' => __('listings.no_streets'),
                     'sqm' => __('dashboard.area_unit_sqm'),
                     'metre' => __('portal.metre_short'),
                 ]))">
            <div x-ref="map" class="absolute inset-0 w-full h-full"></div>
            <p class="absolute top-3 end-3 z-10 text-xs font-medium px-3 py-1.5 rounded-full bg-primary/85 text-white">
                {{ __('listings.map_hint') }}
            </p>
        </section>
    @endif

    @if ($listings === [])
        <div class="flex flex-col items-center justify-center gap-3 py-16 rounded-2xl border border-dashed border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container">
            <span class="material-symbols-outlined text-[48px] opacity-40">storefront</span>
            <p class="text-sm">{{ __('listings.empty') }}</p>
        </div>
    @else
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('listings.count', ['count' => $page->total()]) }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($listings as $listing)
                @php $place = collect([$listing['district'], $listing['city']])->filter()->implode(' · '); @endphp
                <a href="{{ route('portal.listings.show', $listing['id']) }}"
                   class="flex flex-col h-full rounded-2xl p-4 bg-surface-container-lowest dark:bg-[#1a1f2e] border border-outline-variant dark:border-white/10 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center h-36 rounded-xl bg-surface-container-low dark:bg-white/5">
                        <x-parcel-shape :geojson="$listing['geojson']" class="w-28 h-28" />
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm flex-1">
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.area') }}</dt>
                            <dd class="font-bold data-tabular">{{ $listing['area'] !== null ? number_format($listing['area']).' '.__('dashboard.area_unit_sqm') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.asset_type') }}</dt>
                            <dd class="font-medium">{{ $listing['asset_type'] ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.streets') }}</dt>
                            <dd class="font-medium truncate">{{ collect($listing['streets'])->map(fn ($s) => $s['side'].($s['width'] ? ' '.rtrim(rtrim(number_format($s['width'], 1), '0'), '.').' '.__('portal.metre_short') : ''))->implode('، ') ?: __('listings.no_streets') }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.place') }}</dt>
                            <dd class="font-medium truncate">{{ $place ?: '—' }}</dd>
                        </div>
                    </dl>
                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-secondary">
                        {{ __('listings.view') }}<span class="material-symbols-outlined text-[18px] rtl:rotate-180">arrow_forward</span>
                    </span>
                </a>
            @endforeach
        </div>

        <div>{{ $page->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
@include('portal.listings._market-map-script')
@endpush
