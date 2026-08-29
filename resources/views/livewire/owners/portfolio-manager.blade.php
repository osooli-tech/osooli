<div class="space-y-4">

    {{-- Portfolio cards --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container uppercase tracking-wide">
                {{ __('owners.portfolios') }}
            </p>
            <form wire:submit="createPortfolio" class="flex items-center gap-2">
                <input type="text" wire:model="newPortfolioName"
                       placeholder="{{ __('owners.portfolio_name') }}"
                       class="w-40 px-2.5 py-1.5 text-xs rounded-lg
                              bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10
                              text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40">
                <button type="submit"
                        class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg
                               bg-secondary/10 text-secondary hover:bg-secondary/20 transition-colors">
                    <span class="material-symbols-outlined text-[14px]">add</span>
                    {{ __('owners.add_portfolio') }}
                </button>
            </form>
        </div>
        @error('newPortfolioName') <p class="text-xs text-error mb-2">{{ $message }}</p> @enderror

        @if ($portfolios->isEmpty())
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container py-2">
                {{ __('owners.no_portfolios') }}
            </p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach ($portfolios as $row)
                    @php [$portfolio, $summary] = [$row['portfolio'], $row['summary']]; @endphp
                    <div class="bg-surface dark:bg-[#1a2435] rounded-xl p-3 border border-outline-variant dark:border-white/10">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            @if (isset($renaming[$portfolio->id]))
                                <input type="text" wire:model="renaming.{{ $portfolio->id }}"
                                       wire:keydown.enter="renamePortfolio({{ $portfolio->id }})"
                                       wire:blur="renamePortfolio({{ $portfolio->id }})"
                                       class="flex-1 min-w-0 px-1.5 py-0.5 text-sm font-semibold rounded
                                              bg-surface-container dark:bg-white/5 border border-secondary/40
                                              text-on-surface dark:text-white focus:outline-none">
                            @else
                                <button type="button" wire:click="$set('renaming.{{ $portfolio->id }}', @js($portfolio->name))"
                                        class="font-semibold text-sm text-on-surface dark:text-white truncate text-start hover:underline">
                                    {{ $portfolio->name }}
                                </button>
                            @endif
                            <button type="button" wire:click="deletePortfolio({{ $portfolio->id }})"
                                    wire:confirm="{{ __('owners.confirm_delete_portfolio') }}"
                                    class="text-on-surface-variant dark:text-on-primary-container hover:text-error shrink-0">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant dark:text-on-primary-container">
                            <span class="data-tabular">{{ $summary['parcels'] }} {{ __('owners.portfolio_parcels_unit') }}</span>
                            <span class="data-tabular">{{ number_format($summary['area'] / 1000000, 2) }} {{ __('dashboard.area_unit_km') }}</span>
                        </div>
                        @if ($summary['value'] !== null)
                            <p class="text-sm font-bold text-secondary data-tabular mt-1">
                                {{ number_format($summary['value'], 0) }}
                                <span class="text-[11px] font-normal text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.currency') }}</span>
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Parcel assignment --}}
    <div>
        <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container uppercase tracking-wide mb-2">
            {{ __('owners.assign_parcels') }}
        </p>
        <div class="overflow-x-auto rounded-xl border border-outline-variant dark:border-white/10">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-surface dark:bg-[#1a2435] border-b border-outline-variant dark:border-white/10">
                        <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.parcel_no') }}</th>
                        <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.district') }}</th>
                        <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('owners.portfolio') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/5">
                    @forelse ($parcelRows as $row)
                        @php [$parcel, $portfolioId] = [$row['parcel'], $row['portfolio_id']]; @endphp
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <td class="px-3 py-2 font-medium text-on-surface dark:text-white data-tabular">{{ $parcel->parcel_no }}</td>
                            <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container">{{ $parcel->plan?->district?->name_ar ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <select wire:change="assign({{ $parcel->id }}, $event.target.value)"
                                        class="px-2 py-1 text-xs rounded-lg bg-surface dark:bg-[#1a2435]
                                               border border-outline-variant dark:border-white/10
                                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40">
                                    <option value="" @selected($portfolioId === null)>{{ __('owners.unassigned') }}</option>
                                    @foreach ($portfolios as $row2)
                                        <option value="{{ $row2['portfolio']->id }}" @selected($portfolioId === $row2['portfolio']->id)>
                                            {{ $row2['portfolio']->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-on-surface-variant dark:text-on-primary-container">{{ __('owners.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
