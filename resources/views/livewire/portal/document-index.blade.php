@php
    $typeMeta = [
        'صك' => ['icon' => 'description', 'grad' => 'from-primary to-surface-tint', 'tone' => 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90'],
        'كروكي مساحي' => ['icon' => 'straighten', 'grad' => 'from-tertiary to-tertiary-container', 'tone' => 'bg-tertiary-container/30 text-on-surface dark:text-white/90'],
        'جوية' => ['icon' => 'flight', 'grad' => 'from-secondary to-secondary-fixed-dim', 'tone' => 'bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90'],
        'أرضية' => ['icon' => 'landscape', 'grad' => 'from-on-secondary-fixed-variant to-secondary', 'tone' => 'bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90'],
    ];
@endphp
<div class="space-y-4">

    {{-- Summary: total, then one tile per document type — click to filter --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <button type="button" wire:click="$set('filterPhotoType', '')"
                class="text-start rounded-2xl p-4 border shadow-sm transition-all
                       {{ $filterPhotoType === '' ? 'bg-gradient-to-br from-primary to-primary-container text-white border-primary shadow-lg' : 'bg-surface-container-lowest dark:bg-[#1a1f2e] border-outline-variant dark:border-white/10 hover:border-primary/40 hover:-translate-y-0.5' }}">
            <span class="material-symbols-outlined text-[22px] opacity-80">folder_open</span>
            <p class="text-2xl font-bold data-tabular mt-1">{{ number_format($totalDocuments) }}</p>
            <p class="text-xs opacity-80">{{ __('documents.all') }}</p>
        </button>
        @foreach ($photoTypeOptions as $value => $label)
            <button type="button" wire:click="$set('filterPhotoType', '{{ $value }}')"
                    class="text-start rounded-2xl p-4 border shadow-sm transition-all
                           {{ $filterPhotoType === $value ? 'bg-gradient-to-br '.($typeMeta[$value]['grad'] ?? 'from-primary to-primary-container').' text-white border-transparent shadow-lg' : 'bg-surface-container-lowest dark:bg-[#1a1f2e] border-outline-variant dark:border-white/10 hover:border-primary/40 hover:-translate-y-0.5' }}">
                <span class="material-symbols-outlined text-[22px] opacity-80">{{ $typeMeta[$value]['icon'] ?? 'description' }}</span>
                <p class="text-2xl font-bold data-tabular mt-1">{{ number_format((int) ($counts[$value] ?? 0)) }}</p>
                <p class="text-xs opacity-80">{{ $label }}</p>
            </button>
        @endforeach
    </div>

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">search</span>
                    <input wire:model.live.debounce.400ms="search" type="text"
                           placeholder="{{ __('documents.search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            <div class="min-w-[150px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.photo_type') }}
                </label>
                <select wire:model.live="filterPhotoType"
                        class="w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                               focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('documents.all') }}</option>
                    @foreach ($photoTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <x-table.created-filter />

            @if ($search !== '' || $filterPhotoType !== '' || $this->filteringByCreatedAt())
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
                    <tr class="border-b border-outline-variant dark:border-white/10 bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('documents.parcel_no') }}</th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('documents.plan_no') }}</th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('documents.photo_type') }}</th>
                        <x-table.created-header :sort="$createdSort" :label="__('documents.upload_date')" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($photos as $photo)
                        <tr wire:key="doc-{{ $photo->id }}" class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 font-semibold data-tabular">
                                @if ($photo->parcel)
                                    <a href="{{ route('portal.parcels.show', $photo->parcel) }}" class="text-primary hover:underline">{{ $photo->parcel->parcel_no ?? '—' }}</a>
                                @else — @endif
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $photo->parcel?->plan?->plan_no ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($photo->photo_type)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $typeMeta[$photo->photo_type->value]['tone'] ?? '' }}">
                                        <span class="material-symbols-outlined text-[13px]">{{ $typeMeta[$photo->photo_type->value]['icon'] ?? 'description' }}</span>
                                        {{ __('documents.photo_types.'.$photo->photo_type->value) }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                @endif
                            </td>
                            <x-table.created-cell :date="$photo->created_at" />
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('portal.documents.download', $photo) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-xl bg-primary text-white hover:brightness-110 transition-all">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        {{ __('dashboard.view_document') }}
                                    </a>
                                    <a href="{{ route('portal.documents.download', $photo) }}" download
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-xl bg-secondary text-white hover:brightness-110 transition-all">
                                        <span class="material-symbols-outlined text-[15px]">download</span>
                                        {{ __('documents.download') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">folder_open</span>
                                    <p class="text-sm">{{ __('documents.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($photos->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">{{ $photos->links() }}</div>
        @endif
    </div>
</div>
