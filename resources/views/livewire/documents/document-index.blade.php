<div class="space-y-4">

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('documents.search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            {{-- Photo type filter --}}
            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.photo_type') }}
                </label>
                <select wire:model.live="filterPhotoType"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('documents.all') }}</option>
                    @foreach ($photoTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Clear filters --}}
            @if ($search !== '' || $filterPhotoType !== '')
                <button wire:click="clearFilters"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                               text-error border border-error/30 hover:bg-error/10 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('documents.clear_filters') }}
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
                            {{ __('documents.parcel_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.plan_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.photo_type') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.upload_date') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($photos as $photo)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            {{-- Parcel No --}}
                            <td class="px-4 py-3 font-semibold text-on-surface dark:text-white data-tabular">
                                {{ $photo->parcel?->parcel_no ?? '—' }}
                            </td>

                            {{-- Plan No --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $photo->parcel?->plan?->plan_no ?? '—' }}
                            </td>

                            {{-- Photo type --}}
                            <td class="px-4 py-3">
                                @if ($photo->photo_type)
                                    @php
                                        $isAerial = $photo->photo_type === \App\Enums\PhotoType::Aerial;
                                    @endphp
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 {{ $isAerial
                                                     ? 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90'
                                                     : 'bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90' }}">
                                        <span class="material-symbols-outlined text-[13px]">
                                            {{ $isAerial ? 'flight' : 'landscape' }}
                                        </span>
                                        {{ __('documents.photo_types.'.$photo->photo_type->value) }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                @endif
                            </td>

                            {{-- Upload date --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $photo->created_at?->format('Y-m-d') ?? '—' }}
                            </td>

                            {{-- Download --}}
                            <td class="px-4 py-3">
                                <a href="{{ route('documents.download', $photo) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-xl
                                          bg-secondary text-white hover:brightness-110 transition-all">
                                    <span class="material-symbols-outlined text-[15px]">download</span>
                                    {{ __('documents.download') }}
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">folder_open</span>
                                    <p class="text-sm">{{ __('documents.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($photos->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $photos->links() }}
            </div>
        @endif

    </div>

</div>
