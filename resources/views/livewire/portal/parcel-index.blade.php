<div class="space-y-4">

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('parcels.search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('parcels.search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('parcels.asset_type') }}
                </label>
                <select wire:model.live="filterAssetType"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('parcels.all') }}</option>
                    @foreach ($assetTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('parcels.land_transaction') }}
                </label>
                <select wire:model.live="filterLandTransaction"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('parcels.all') }}</option>
                    @foreach ($landTransactionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[130px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('parcels.deed_status') }}
                </label>
                <select wire:model.live="filterDeedStatus"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('parcels.all') }}</option>
                    @foreach ($deedStatusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[130px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('parcels.pricing') }}
                </label>
                <select wire:model.live="filterPricing"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('parcels.all') }}</option>
                    <option value="priced">{{ __('parcels.priced') }}</option>
                    <option value="unpriced">{{ __('parcels.unpriced') }}</option>
                </select>
            </div>

            <x-table.created-filter />

            @if ($search !== '' || $filterAssetType !== '' || $filterLandTransaction !== '' || $filterDeedStatus !== '' || $filterPricing !== '' || $this->filteringByCreatedAt())
                <button wire:click="clearFilters"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                               text-error border border-error/30 hover:bg-error/10 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('parcels.clear_filters') }}
                </button>
            @endif

            {{-- Cards / table switch --}}
            <div class="flex rounded-xl border border-outline-variant dark:border-white/10 overflow-hidden ms-auto">
                @foreach (['cards' => 'grid_view', 'table' => 'table_rows'] as $mode => $icon)
                    <button type="button" wire:click="$set('view', '{{ $mode }}')" title="{{ __('portal.view_'.$mode) }}"
                            class="flex items-center gap-1 px-3 py-2 text-xs font-medium transition-colors
                                   {{ $view === $mode ? 'bg-primary text-white' : 'text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5' }}">
                        <span class="material-symbols-outlined text-[16px]">{{ $icon }}</span>
                        {{ __('portal.view_'.$mode) }}
                    </button>
                @endforeach
            </div>

            @if ($view === 'table' && collect($populated)->contains(false))
                <button wire:click="$toggle('showAllColumns')"
                        class="flex items-center gap-1.5 px-3 py-2 text-xs rounded-xl
                               border border-outline-variant dark:border-white/20
                               text-on-surface-variant dark:text-on-primary-container
                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-[15px]">
                        {{ $showAllColumns ? 'visibility_off' : 'visibility' }}
                    </span>
                    {{ $showAllColumns ? __('parcels.hide_empty_columns') : __('parcels.show_all_columns') }}
                </button>
            @endif

        </div>
    </div>

    @if ($view === 'cards')
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
            @forelse ($parcels as $parcel)
                @php
                    $deed = $parcel->latestDeed;
                    $isUpdated = $deed?->deed_status === \App\Enums\DeedStatus::Updated->value;
                    $frontage = \App\Support\ParcelFrontage::read($parcel->boundary);
                    $facing = $frontage ? \App\Support\ParcelFrontage::facingLabel($frontage) : null;
                @endphp
                <a wire:key="card-{{ $parcel->id }}" href="{{ route('portal.parcels.show', $parcel) }}"
                   class="group relative overflow-hidden flex flex-col h-full bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4 pt-5
                          border border-outline-variant dark:border-white/10 shadow-sm hover:border-secondary/50 hover:shadow-lg hover:-translate-y-1 transition-all">
                    <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-l {{ $frontage && $frontage['is_corner'] ? 'from-tertiary-container to-tertiary-fixed' : 'from-secondary to-secondary-fixed-dim' }}"></span>
                    @if ($frontage && $frontage['is_corner'])
                        <span class="absolute top-3 end-3 inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-tertiary-container text-on-tertiary-container">
                            <span class="material-symbols-outlined text-[13px]">turn_right</span>{{ __('portal.lot_corner') }}
                        </span>
                    @endif
                    <div class="flex items-start gap-3">
                        <div class="w-20 h-20 rounded-xl bg-surface-container dark:bg-white/5 flex items-center justify-center shrink-0">
                            <x-parcel-shape :geojson="$parcel->getAttribute('geom_json')" class="w-16 h-16 transition-transform group-hover:scale-110" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.parcel_no') }}</p>
                            <p class="text-xl font-bold data-tabular leading-tight">{{ $parcel->parcel_no ?? '—' }}</p>
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container truncate mt-0.5">
                                {{ $parcel->plan?->district?->name_ar ?? '—' }} · {{ __('parcels.plan_no') }} <span class="data-tabular">{{ $parcel->plan?->plan_no ?? '—' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @if ($parcel->asset_type)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90">{{ __('parcels.asset_types.'.$parcel->asset_type) }}</span>
                        @endif
                        @if ($deed?->deed_status)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $isUpdated ? 'bg-secondary/10 text-secondary' : 'bg-error/10 text-error' }}">{{ __('parcels.deed_statuses.'.$deed->deed_status) }}</span>
                        @endif
                        @if ($facing)
                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[11px] bg-tertiary-container/20 text-on-surface dark:text-white">
                                <span class="material-symbols-outlined text-[13px]">explore</span>{{ $facing }}@if ($frontage['widest_street']) · {{ rtrim(rtrim(number_format($frontage['widest_street'], 1), '0'), '.') }} {{ __('portal.metre_short') }}@endif
                            </span>
                        @endif
                        @if ($deed?->deed_no)
                            <span class="px-2 py-0.5 rounded-full text-[11px] bg-surface-container dark:bg-white/5 data-tabular" dir="ltr">{{ __('parcels.deed_no') }} {{ $deed->deed_no }}</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-auto pt-4 text-sm">
                        <div>
                            <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.area_deed') }}</p>
                            <p class="font-semibold data-tabular">{{ $deed?->deed_area ? number_format((float) $deed->deed_area).' '.__('dashboard.area_unit_sqm') : '—' }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.parcel_price') }}</p>
                            <p class="font-bold text-secondary data-tabular">{{ $parcel->parcel_price === null ? '—' : number_format((float) $parcel->parcel_price).' '.__('parcels.sar') }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full flex flex-col items-center gap-3 py-16 text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[48px] opacity-30">search_off</span>
                    <p class="text-sm">{{ __('parcels.no_results') }}</p>
                </div>
            @endforelse
        </div>

        @if ($parcels->hasPages())
            <div>{{ $parcels->links() }}</div>
        @endif
    @else
    @php
        $colCount = 3; // parcel_no + plan_no + created_at (always visible)
        $colCount += ($showAllColumns || $populated['asset_type'])      ? 1 : 0;
        $colCount += ($showAllColumns || $populated['land_transaction']) ? 1 : 0;
        $colCount += ($showAllColumns || $populated['district'])         ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_no'])          ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_date'])        ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_area'])        ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_status'])      ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_class'])       ? 1 : 0;
        $colCount += ($showAllColumns || $populated['m_price'])          ? 1 : 0;
        $colCount += ($showAllColumns || $populated['parcel_price'])     ? 1 : 0;
    @endphp

    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10
                                bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('parcels.parcel_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('parcels.plan_no') }}
                        </th>
                        @if ($showAllColumns || $populated['asset_type'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.asset_type') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['land_transaction'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.land_transaction') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['district'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.district') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['deed_no'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_no') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['deed_date'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_date') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['deed_area'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.area_deed') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['deed_status'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_status') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['deed_class'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_class') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['m_price'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.m_price') }}</th>
                        @endif
                        @if ($showAllColumns || $populated['parcel_price'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.parcel_price') }}</th>
                        @endif
                        <x-table.created-header :sort="$createdSort" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($parcels as $parcel)
                        <tr wire:key="parcel-{{ $parcel->id }}"
                            onclick="window.location = '{{ route('portal.parcels.show', $parcel) }}'"
                            class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors cursor-pointer">

                            <td class="px-4 py-3 font-semibold text-on-surface dark:text-white data-tabular">
                                {{ $parcel->parcel_no ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $parcel->plan?->plan_no ?? '—' }}
                            </td>

                            @if ($showAllColumns || $populated['asset_type'])
                                <td class="px-4 py-3">
                                    @if ($parcel->asset_type)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                     bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90">
                                            {{ __('parcels.asset_types.'.$parcel->asset_type) }}
                                        </span>
                                    @else
                                        <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                    @endif
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['land_transaction'])
                                <td class="px-4 py-3">
                                    @if ($parcel->land_transaction)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                     bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90">
                                            {{ __('parcels.land_transactions.'.$parcel->land_transaction) }}
                                        </span>
                                    @else
                                        <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                    @endif
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['district'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                    {{ app()->isLocale('ar')
                                        ? ($parcel->plan?->district?->name_ar ?? '—')
                                        : ($parcel->plan?->district?->name_en ?? '—') }}
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['deed_no'])
                                <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                    {{ $parcel->latestDeed?->deed_no ?? '—' }}
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['deed_date'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                    {{ $parcel->latestDeed?->deed_date_hijri ?? '—' }}
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['deed_area'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                    @if ($parcel->latestDeed?->deed_area)
                                        {{ number_format((float) $parcel->latestDeed->deed_area, 0) }} {{ __('dashboard.area_unit_sqm') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['deed_status'])
                                <td class="px-4 py-3">
                                    @if ($parcel->latestDeed?->deed_status)
                                        @php($isUpdated = $parcel->latestDeed->deed_status === \App\Enums\DeedStatus::Updated->value)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                     {{ $isUpdated
                                                         ? 'bg-secondary/10 text-secondary dark:bg-secondary/20'
                                                         : 'bg-error/10 text-error dark:bg-error/20' }}">
                                            {{ __('parcels.deed_statuses.'.$parcel->latestDeed->deed_status) }}
                                        </span>
                                    @else
                                        <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                    @endif
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['deed_class'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                    {{ $parcel->latestDeed?->deed_class ?? '—' }}
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['m_price'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                    {{ $parcel->m_price === null ? '—' : number_format((float) $parcel->m_price) }}
                                </td>
                            @endif

                            @if ($showAllColumns || $populated['parcel_price'])
                                <td class="px-4 py-3 font-semibold text-secondary data-tabular">
                                    {{ $parcel->parcel_price === null ? '—' : number_format((float) $parcel->parcel_price) }}
                                </td>
                            @endif

                            <x-table.created-cell :date="$parcel->created_at" />
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $colCount }}" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">search_off</span>
                                    <p class="text-sm">{{ __('parcels.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($parcels->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $parcels->links() }}
            </div>
        @endif

    </div>
    @endif

</div>
