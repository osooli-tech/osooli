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
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.measured_area') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('survey_decisions.matches_deed') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($decisions as $decision)
                        @php
                            $boundary = $decision->parcel?->boundary;
                            $surveyDocument = $decision->parcel?->photos
                                ->firstWhere('photo_type', \App\Enums\PhotoType::BoundarySurvey);
                        @endphp
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors align-top">

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

                            {{-- Measured area (from parcel_boundaries) --}}
                            <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                {{ $boundary?->measured_area ? number_format((float) $boundary->measured_area, 2).' '.__('dashboard.area_unit_sqm') : '—' }}
                            </td>

                            {{-- Match status --}}
                            <td class="px-4 py-3">
                                @if ($boundary?->matches_deed === true)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90">
                                        {{ __('survey_decisions.matches_deed_yes') }}
                                    </span>
                                @elseif ($boundary?->matches_deed === false)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 bg-error/10 text-error dark:bg-error/20 dark:text-white/90">
                                        {{ __('survey_decisions.matches_deed_no') }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">
                                        {{ __('survey_decisions.matches_deed_unknown') }}
                                    </span>
                                @endif
                            </td>

                            {{-- Link to parcel + survey document --}}
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1.5">
                                    @if ($decision->parcel)
                                        <a href="{{ route('parcels.show', $decision->parcel) }}"
                                           class="inline-flex items-center gap-1 text-xs font-medium text-primary
                                                  hover:underline underline-offset-2 transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">arrow_back_ios</span>
                                            {{ __('survey_decisions.view_parcel') }}
                                        </a>
                                    @endif
                                    @can('documents.download')
                                        @if ($surveyDocument)
                                            <a href="{{ route('documents.download', $surveyDocument) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1 text-xs font-medium text-secondary
                                                      hover:underline underline-offset-2 transition-colors">
                                                <span class="material-symbols-outlined text-[15px]">description</span>
                                                {{ __('survey_decisions.view_document') }}
                                            </a>
                                        @endif
                                    @endcan
                                </div>
                            </td>

                        </tr>

                        {{-- Boundaries row (borders + dimensions "as surveyed") --}}
                        @if ($boundary && ($boundary->n_border || $boundary->s_border || $boundary->e_border || $boundary->w_border))
                            <tr class="bg-surface-container dark:bg-[#161f2e] border-b border-outline-variant dark:border-white/10">
                                <td colspan="9" class="px-4 py-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-2">
                                        {{ __('survey_decisions.boundaries') }}
                                    </p>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                        <div>
                                            <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.n_border') }}: </span>
                                            <span class="text-on-surface dark:text-white">{{ $boundary->n_border ?? '—' }} ({{ $boundary->n_dim ?? '—' }})</span>
                                        </div>
                                        <div>
                                            <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.s_border') }}: </span>
                                            <span class="text-on-surface dark:text-white">{{ $boundary->s_border ?? '—' }} ({{ $boundary->s_dim ?? '—' }})</span>
                                        </div>
                                        <div>
                                            <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.e_border') }}: </span>
                                            <span class="text-on-surface dark:text-white">{{ $boundary->e_border ?? '—' }} ({{ $boundary->e_dim ?? '—' }})</span>
                                        </div>
                                        <div>
                                            <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.w_border') }}: </span>
                                            <span class="text-on-surface dark:text-white">{{ $boundary->w_border ?? '—' }} ({{ $boundary->w_dim ?? '—' }})</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center">
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
