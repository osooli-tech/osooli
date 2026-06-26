<div class="space-y-4">

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('survey_decisions.search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('survey_decisions.search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            {{-- Qrar source filter --}}
            <div class="min-w-[160px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('survey_decisions.qrar_source') }}
                </label>
                <select wire:model.live="filterQrarSource"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('survey_decisions.all') }}</option>
                    @foreach ($qrarSourceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Clear filters --}}
            @if ($search !== '' || $filterQrarSource !== '')
                <button wire:click="clearFilters"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                               text-error border border-error/30 hover:bg-error/10 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('survey_decisions.clear_filters') }}
                </button>
            @endif

        </div>
    </div>

    {{-- Table --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10
                                bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.parcel_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.plan_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.folder') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.report_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.qrar_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.qrar_source') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($decisions as $decision)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            {{-- Parcel No --}}
                            <td class="px-4 py-3 font-semibold text-on-surface dark:text-white data-tabular">
                                {{ $decision->parcel?->parcel_no ?? '—' }}
                            </td>

                            {{-- Plan No --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $decision->parcel?->plan?->plan_no ?? '—' }}
                            </td>

                            {{-- Folder --}}
                            <td class="px-4 py-3 text-on-surface dark:text-white">
                                {{ $decision->folder ?? '—' }}
                            </td>

                            {{-- Report No --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $decision->report_no ?? '—' }}
                            </td>

                            {{-- Qrar No --}}
                            <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                {{ $decision->qrar_no ?? '—' }}
                            </td>

                            {{-- Qrar Source --}}
                            <td class="px-4 py-3">
                                @if ($decision->qrar_source)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90">
                                        {{ __('survey_decisions.qrar_sources.'.$decision->qrar_source->value) }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                @endif
                            </td>

                            {{-- Link to parcel --}}
                            <td class="px-4 py-3">
                                @if ($decision->parcel)
                                    <a href="{{ route('parcels.show', $decision->parcel) }}"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-primary
                                              hover:underline underline-offset-2 transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">arrow_back_ios</span>
                                        {{ __('survey_decisions.view_parcel') }}
                                    </a>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">fact_check</span>
                                    <p class="text-sm">{{ __('survey_decisions.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($decisions->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $decisions->links() }}
            </div>
        @endif

    </div>

</div>
