<div class="space-y-4">

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            {{-- Search --}}
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

            {{-- Asset type filter --}}
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

            {{-- Land transaction filter --}}
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

            {{-- Deed status filter --}}
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

            {{-- Clear filters --}}
            @if ($search !== '' || $filterAssetType !== '' || $filterLandTransaction !== '' || $filterDeedStatus !== '')
                <button wire:click="clearFilters"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                               text-error border border-error/30 hover:bg-error/10 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('parcels.clear_filters') }}
                </button>
            @endif

            {{-- Toggle hidden columns — only shown when some columns are currently hidden --}}
            @if (collect($populated)->contains(false))
                <button wire:click="$toggle('showAllColumns')"
                        class="ms-auto flex items-center gap-1.5 px-3 py-2 text-xs rounded-xl
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

    {{-- Compute visible column count for empty-state colspan --}}
    @php
        $colCount = 3; // parcel_no + plan_no + actions (always visible)
        $colCount += ($showAllColumns || $populated['asset_type'])      ? 1 : 0;
        $colCount += ($showAllColumns || $populated['land_transaction']) ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_no'])          ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_area'])        ? 1 : 0;
        $colCount += ($showAllColumns || $populated['deed_status'])      ? 1 : 0;
    @endphp

    {{-- Table --}}
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
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.asset_type') }}
                            </th>
                        @endif
                        @if ($showAllColumns || $populated['land_transaction'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.land_transaction') }}
                            </th>
                        @endif
                        @if ($showAllColumns || $populated['deed_no'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.deed_no') }}
                            </th>
                        @endif
                        @if ($showAllColumns || $populated['deed_area'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.area_deed') }}
                            </th>
                        @endif
                        @if ($showAllColumns || $populated['deed_status'])
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.deed_status') }}
                            </th>
                        @endif
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($parcels as $parcel)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            {{-- Parcel No --}}
                            <td class="px-4 py-3 font-semibold text-on-surface dark:text-white data-tabular">
                                {{ $parcel->parcel_no ?? '—' }}
                            </td>

                            {{-- Plan No --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $parcel->plan?->plan_no ?? '—' }}
                            </td>

                            {{-- Asset type --}}
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

                            {{-- Land transaction --}}
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

                            {{-- Deed No --}}
                            @if ($showAllColumns || $populated['deed_no'])
                                <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                    {{ $parcel->latestDeed?->deed_no ?? '—' }}
                                </td>
                            @endif

                            {{-- Deed Area --}}
                            @if ($showAllColumns || $populated['deed_area'])
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                    @if ($parcel->latestDeed?->deed_area)
                                        {{ number_format((float) $parcel->latestDeed->deed_area, 0) }}
                                        {{ __('dashboard.area_unit_sqm') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif

                            {{-- Deed Status --}}
                            @if ($showAllColumns || $populated['deed_status'])
                                <td class="px-4 py-3">
                                    @if ($parcel->latestDeed?->deed_status)
                                        @php
                                            $isUpdated = $parcel->latestDeed->deed_status === \App\Enums\DeedStatus::Updated->value;
                                        @endphp
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

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <a href="{{ route('parcels.show', $parcel) }}"
                                   class="inline-flex items-center gap-1 text-xs font-medium text-primary
                                          hover:underline underline-offset-2 transition-colors">
                                    <span class="material-symbols-outlined text-[15px]">arrow_back_ios</span>
                                    {{ __('parcels.show') }}
                                </a>
                            </td>

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

        {{-- Pagination --}}
        @if ($parcels->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $parcels->links() }}
            </div>
        @endif

    </div>

</div>
