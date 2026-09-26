@extends('portal.layout')

@section('title', __('portal.nav_parcels'))

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-xl font-semibold">{{ __('portal.nav_parcels') }}</h1>

        <form method="GET" class="relative w-full sm:w-72">
            <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px] text-on-surface-variant pointer-events-none">search</span>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="{{ __('portal.parcels_search_placeholder') }}"
                   class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                          bg-surface-container dark:bg-[#1a1f2e]
                          border border-outline-variant dark:border-white/10
                          text-on-surface dark:text-white
                          placeholder:text-on-surface-variant focus:outline-none
                          focus:ring-2 focus:ring-primary/40">
        </form>
    </div>

    @if ($parcels->isEmpty())
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-10 text-center
                    border border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container">
            {{ __('portal.no_parcels') }}
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($parcels as $parcel)
                <a href="{{ route('portal.parcels.show', $parcel) }}"
                   class="block bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                          border border-outline-variant dark:border-white/10 shadow-sm
                          hover:border-secondary/50 transition-colors">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="font-semibold data-tabular">{{ __('parcels.parcel_no') }} {{ $parcel->parcel_no }}</span>
                        @if ($parcel->asset_type)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90">
                                {{ $parcel->asset_type }}
                            </span>
                        @endif
                    </div>
                    <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
                        {{ $parcel->plan?->district?->name_ar ?? '—' }} — {{ $parcel->plan?->district?->city?->name_ar ?? '—' }}
                    </p>
                    <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-1 ltr" dir="ltr">
                        {{ $parcel->geo_id }}
                    </p>
                </a>
            @endforeach
        </div>

        <div>{{ $parcels->links() }}</div>
    @endif

</div>
@endsection
