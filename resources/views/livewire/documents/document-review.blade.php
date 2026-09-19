<div class="space-y-4">

    {{-- Filter bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.review_search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('documents.review_search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            <div class="min-w-[170px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('documents.status') }}
                </label>
                <select wire:model.live="filterStatus"
                        class="w-full px-3 py-2 text-sm rounded-xl
                               bg-surface-container dark:bg-[#252b3b]
                               border border-outline-variant dark:border-white/10
                               text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <option value="">{{ __('documents.all') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}">{{ __('documents.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </div>

        </div>
    </div>

    {{-- Queue --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10
                               bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.file_name') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.parcel_no') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.photo_type') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.uploaded_by') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('documents.status') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($documents as $document)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]
                                                 text-on-surface-variant dark:text-on-primary-container">description</span>
                                    <div class="min-w-0">
                                        <p class="text-on-surface dark:text-white truncate max-w-[260px]">
                                            {{ $document->downloadName() }}
                                        </p>
                                        @if ($document->size_bytes !== null)
                                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                                {{ number_format($document->size_bytes / 1024, 0) }} KB
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 font-semibold text-on-surface dark:text-white data-tabular">
                                {{ $document->parcel?->parcel_no ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $document->photo_type
                                    ? __('documents.photo_types.'.$document->photo_type->value)
                                    : '—' }}
                            </td>

                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $reviewers[$document->uploaded_by] ?? __('documents.unknown_user') }}
                            </td>

                            {{-- Status, plus the reason and reviewer behind a decided one:
                                 the queue is also where someone comes to find out why a
                                 document they uploaded came back. --}}
                            <td class="px-4 py-3">
                                @php
                                    $badge = match ($document->status) {
                                        \App\Models\ParcelPhoto::STATUS_APPROVED => 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90',
                                        \App\Models\ParcelPhoto::STATUS_REJECTED => 'bg-error/10 text-error dark:bg-error/20 dark:text-white/90',
                                        default => 'bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ __('documents.statuses.'.$document->status) }}
                                </span>

                                @if ($document->rejection_reason !== null)
                                    <p class="mt-1 text-xs text-error max-w-[260px]">
                                        {{ $document->rejection_reason }}
                                    </p>
                                @endif

                                @if ($document->reviewed_at !== null)
                                    <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                        {{ $reviewers[$document->reviewed_by] ?? __('documents.unknown_user') }}
                                        &middot;
                                        {{ $document->reviewed_at->format('Y-m-d') }}
                                    </p>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @can('documents.download')
                                        <a href="{{ route('documents.download', $document) }}"
                                           title="{{ __('documents.download') }}"
                                           class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                                  hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">download</span>
                                        </a>
                                    @endcan

                                    @if ($document->isPending())
                                        <button type="button"
                                                wire:click="approve({{ $document->id }})"
                                                wire:loading.attr="disabled"
                                                class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                                                       bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">check</span>
                                            {{ __('documents.approve') }}
                                        </button>

                                        <button type="button"
                                                wire:click="confirmReject({{ $document->id }})"
                                                class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                                                       text-error border border-error/30 hover:bg-error/10 transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">close</span>
                                            {{ __('documents.reject') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center
                                                   text-on-surface-variant dark:text-on-primary-container">
                                {{ __('documents.no_documents_to_review') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- Rejection dialog. The reason is required, so there is no way to send a
         document back without telling the uploader what to fix. --}}
    @if ($rejectingId !== null)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelReject"></div>

            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-md bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ __('documents.reject_title') }}
                </h2>

                <form wire:submit="reject" class="space-y-4">
                    <div>
                        <label for="rejectionReason"
                               class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('documents.rejection_reason') }}
                            <span class="text-error">*</span>
                        </label>

                        <textarea id="rejectionReason"
                                  wire:model="rejectionReason"
                                  rows="4"
                                  class="w-full px-3 py-2 text-sm rounded-xl
                                         bg-surface-container dark:bg-[#252b3b]
                                         border border-outline-variant dark:border-white/10
                                         text-on-surface dark:text-white
                                         focus:outline-none focus:ring-2 focus:ring-primary/40
                                         @error('rejectionReason') border-error ring-1 ring-error/40 @enderror"></textarea>

                        <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">
                            {{ __('documents.rejection_reason_hint') }}
                        </p>

                        @error('rejectionReason')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="cancelReject"
                                class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                       bg-error text-white hover:brightness-110 transition-all
                                       disabled:opacity-60">
                            <span wire:loading wire:target="reject"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('documents.reject') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
