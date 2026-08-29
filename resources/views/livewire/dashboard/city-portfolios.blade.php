@php
    $totalParcels = array_sum(array_column($portfolios, 'parcels'));
    $font = app()->isLocale('ar') ? 'IBM Plex Sans Arabic' : 'IBM Plex Sans';
    $isDark = "document.documentElement.classList.contains('dark') ? 'dark' : 'light'";
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    {{-- Cards, one per city --}}
    <div class="xl:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
        @forelse ($portfolios as $portfolio)
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-xl p-4
                        border border-outline-variant dark:border-white/10 shadow-sm">

                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="material-symbols-outlined text-[18px] text-tertiary shrink-0"
                              style="font-variation-settings: 'FILL' 1;">location_city</span>
                        <h3 class="font-semibold text-on-surface dark:text-white text-sm truncate">
                            {{ $portfolio['name'] }}
                        </h3>
                    </div>
                    <span class="text-[11px] text-on-surface-variant dark:text-on-primary-container shrink-0 data-tabular">
                        {{ $totalParcels > 0 ? number_format($portfolio['parcels'] / $totalParcels * 100, 1) : '0' }}%
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">
                            {{ __('dashboard.portfolio_parcels') }}
                        </dt>
                        <dd class="font-bold text-on-surface dark:text-white data-tabular">
                            {{ number_format($portfolio['parcels']) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">
                            {{ __('dashboard.portfolio_area') }}
                        </dt>
                        <dd class="font-bold text-on-surface dark:text-white data-tabular">
                            {{ number_format($portfolio['area'] / 1000000, 2) }}
                            <span class="text-[11px] font-normal">{{ __('dashboard.area_unit_km') }}</span>
                        </dd>
                    </div>
                    <div class="col-span-2 pt-2 border-t border-outline-variant dark:border-white/10">
                        <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">
                            {{ __('dashboard.portfolio_value') }}
                        </dt>
                        <dd class="font-bold text-secondary data-tabular">
                            @if ($portfolio['value'] !== null)
                                {{ number_format($portfolio['value'], 0) }}
                                <span class="text-[11px] font-normal text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.currency') }}
                                </span>
                                {{-- Say what the total actually covers rather than implying it is complete. --}}
                                @if ($portfolio['priced'] < $portfolio['parcels'])
                                    <span class="block text-[11px] font-normal text-on-surface-variant dark:text-on-primary-container mt-0.5">
                                        {{ __('dashboard.portfolio_priced_of', [
                                            'priced' => $portfolio['priced'],
                                            'total' => $portfolio['parcels'],
                                        ]) }}
                                    </span>
                                @endif
                            @else
                                <span class="text-sm font-normal text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.not_recorded') }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @empty
            <div class="sm:col-span-2 text-center py-8 text-sm text-on-surface-variant dark:text-on-primary-container">
                {{ __('dashboard.no_data') }}
            </div>
        @endforelse
    </div>

    {{-- Share of parcels across the city portfolios --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <h3 class="font-semibold text-on-surface dark:text-white text-sm mb-3">
            {{ __('dashboard.portfolio_chart_title') }}
        </h3>

        @if ($totalParcels === 0)
            <div class="text-center py-8 text-sm text-on-surface-variant dark:text-on-primary-container">
                {{ __('dashboard.no_data') }}
            </div>
        @else
            <div wire:ignore
                 x-data="{
                     c: null,
                     init() {
                         this.c = new ApexCharts(this.$refs.el, {
                             chart: { type: 'donut', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                             series: @js(array_column($portfolios, 'parcels')),
                             labels: @js(array_column($portfolios, 'name')),
                             colors: ['#006c4e','#c9a84c','#002444','#abc9f2','#8a8f98','#e07b39'],
                             theme: { mode: {{ $isDark }} },
                             legend: { position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}' },
                             dataLabels: { enabled: true, style: { fontSize: '11px' } },
                             plotOptions: { pie: { donut: { size: '58%' } } },
                             stroke: { width: 2 },
                         });
                         this.c.render();
                     }
                 }">
                <div x-ref="el"></div>
            </div>
        @endif
    </div>
</div>
