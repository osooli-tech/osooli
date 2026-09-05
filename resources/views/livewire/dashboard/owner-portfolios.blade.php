@if (empty($portfolios))
    <div class="text-center py-8 text-sm text-on-surface-variant dark:text-on-primary-container
                bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-xl border border-outline-variant dark:border-white/10">
        {{ __('dashboard.no_owner_portfolios') }}
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach ($portfolios as $portfolio)
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-xl p-4
                        border border-outline-variant dark:border-white/10 shadow-sm">

                <div class="flex items-center gap-2 mb-1 min-w-0">
                    <span class="material-symbols-outlined text-[18px] text-tertiary shrink-0"
                          style="font-variation-settings: 'FILL' 1;">folder_special</span>
                    <h3 class="font-semibold text-on-surface dark:text-white text-sm truncate">
                        {{ $portfolio['name'] }}
                    </h3>
                </div>
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container truncate mb-3">
                    {{ $portfolio['owner'] }}
                </p>

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
                            @else
                                <span class="text-sm font-normal text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.not_recorded') }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>

    @if ($totalCount > count($portfolios))
        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-3">
            {{ __('dashboard.owner_portfolios_showing', ['shown' => count($portfolios), 'total' => $totalCount]) }}
            <a href="{{ route('owners.index') }}" class="text-primary hover:underline">{{ __('dashboard.owner_portfolios_view_all') }}</a>
        </p>
    @endif
@endif
