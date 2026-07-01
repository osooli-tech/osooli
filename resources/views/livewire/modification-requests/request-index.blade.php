<div>

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ $this->counts['all'] }} {{ trans_choice('modification_requests.total', $this->counts['all']) }}
        </p>

        {{-- Search --}}
        <div class="relative w-full sm:w-72">
            <span class="absolute inset-y-0 end-3 flex items-center pointer-events-none
                         text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </span>
            <input wire:model.live.debounce.300ms="search"
                   type="search"
                   placeholder="{{ __('modification_requests.search_placeholder') }}"
                   class="w-full pe-10 ps-4 py-2 text-sm rounded-xl
                          bg-surface-container dark:bg-[#252b3b]
                          border border-outline-variant dark:border-white/10
                          text-on-surface dark:text-white
                          focus:outline-none focus:ring-2 focus:ring-secondary/40" />
        </div>
    </div>

    {{-- ── Status tabs ── --}}
    <div class="flex items-center gap-1 flex-wrap mb-5
                bg-surface-container dark:bg-[#252b3b]
                rounded-xl p-1 w-fit">

        @php
            $tabs = [
                'all'            => ['label' => __('modification_requests.status.all'),            'count' => $this->counts['all']],
                'pending'        => ['label' => __('modification_requests.status.pending'),        'count' => $this->counts['pending']],
                'sent_to_arcgis' => ['label' => __('modification_requests.status.sent_to_arcgis'),'count' => $this->counts['sent_to_arcgis']],
                'applied'        => ['label' => __('modification_requests.status.applied'),        'count' => $this->counts['applied']],
                'rejected'       => ['label' => __('modification_requests.status.rejected'),       'count' => $this->counts['rejected']],
            ];
        @endphp

        @foreach ($tabs as $key => $tab)
            <button wire:click="$set('statusFilter', '{{ $key }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                           transition-colors
                           {{ $statusFilter === $key
                               ? 'bg-secondary text-white shadow-sm'
                               : 'text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container-high dark:hover:bg-white/10' }}">
                {{ $tab['label'] }}
                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px]
                             rounded-full text-[10px] font-bold px-1
                             {{ $statusFilter === $key ? 'bg-white/20 text-white' : 'bg-outline-variant/40 dark:bg-white/10' }}">
                    {{ $tab['count'] }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Table ── --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e]
                rounded-2xl border border-outline-variant dark:border-white/10
                shadow-sm overflow-hidden">

        @if ($requests->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 gap-3
                        text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[48px] opacity-30">edit_note</span>
                <p class="text-sm">
                    {{ $statusFilter === 'all'
                        ? __('modification_requests.empty')
                        : __('modification_requests.empty_filtered') }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant dark:border-white/10
                                   text-[11px] font-bold uppercase tracking-wider
                                   text-on-surface-variant dark:text-on-primary-container">
                            <th class="px-5 py-3 text-start">{{ __('modification_requests.col_parcel') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('modification_requests.col_owner') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('modification_requests.col_field') }}</th>
                            <th class="px-5 py-3 text-start hidden md:table-cell">{{ __('modification_requests.col_old') }}</th>
                            <th class="px-5 py-3 text-start hidden md:table-cell">{{ __('modification_requests.col_new') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('modification_requests.col_status') }}</th>
                            <th class="px-5 py-3 text-start hidden lg:table-cell">{{ __('modification_requests.col_date') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('modification_requests.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 dark:divide-white/5">
                        @foreach ($requests as $req)
                            <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                                {{-- Parcel --}}
                                <td class="px-5 py-3.5">
                                    <span class="font-semibold text-on-surface dark:text-white ltr">
                                        {{ $req->parcel?->parcel_no ?? '—' }}
                                    </span>
                                </td>

                                {{-- Owner --}}
                                <td class="px-5 py-3.5 text-on-surface dark:text-white/80">
                                    {{ $req->owner?->name ?? '—' }}
                                </td>

                                {{-- Field --}}
                                <td class="px-5 py-3.5">
                                    <span class="text-xs font-medium text-secondary dark:text-secondary/80
                                                 bg-secondary/10 dark:bg-secondary/20
                                                 px-2 py-0.5 rounded-lg">
                                        {{ $req->fieldLabel() }}
                                    </span>
                                </td>

                                {{-- Old value --}}
                                <td class="px-5 py-3.5 hidden md:table-cell
                                           text-on-surface-variant dark:text-on-primary-container
                                           max-w-[140px] truncate">
                                    {{ $req->old_value ?? '—' }}
                                </td>

                                {{-- New value --}}
                                <td class="px-5 py-3.5 hidden md:table-cell
                                           text-on-surface dark:text-white
                                           max-w-[140px] truncate font-medium">
                                    {{ $req->new_value ?? '—' }}
                                </td>

                                {{-- Status chip --}}
                                <td class="px-5 py-3.5">
                                    @include('livewire.modification-requests._status-chip', ['status' => $req->status])
                                </td>

                                {{-- Date --}}
                                <td class="px-5 py-3.5 hidden lg:table-cell
                                           text-on-surface-variant dark:text-on-primary-container text-xs ltr">
                                    {{ $req->created_at->format('Y-m-d') }}
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-3.5">
                                    <button wire:click="openRequest({{ $req->id }})"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg
                                                   text-xs font-semibold
                                                   bg-secondary/10 dark:bg-secondary/20
                                                   text-secondary dark:text-white/80
                                                   hover:bg-secondary hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                        {{ __('modification_requests.modal_title') }}
                                    </button>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($requests->hasPages())
                <div class="px-5 py-4 border-t border-outline-variant dark:border-white/10">
                    {{ $requests->links() }}
                </div>
            @endif
        @endif

    </div>

    {{-- ── Detail / Action Modal (§0-D: @if + fixed z-[9999], no @teleport) ── --}}
    @if ($showModal && $this->viewing)
        @php $req = $this->viewing @endphp
        <div class="fixed inset-0 z-[9999] overflow-y-auto" wire:key="modal-detail">
            <div class="flex min-h-full items-center justify-center p-4">

                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                     wire:click="closeModal"></div>

                {{-- Panel --}}
                <div class="relative w-full max-w-lg
                            bg-surface-container-lowest dark:bg-[#1a1f2e]
                            rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10
                            overflow-hidden flex flex-col"
                     style="max-height: min(92vh, 680px);">

                    <div class="h-1 bg-secondary shrink-0"></div>

                    {{-- Header --}}
                    <div class="px-6 pt-5 pb-4 border-b border-outline-variant dark:border-white/10 shrink-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                                    {{ __('modification_requests.modal_title') }}
                                </h2>
                                <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5 ltr">
                                    #{{ $req->id }} · {{ $req->created_at->format('Y-m-d H:i') }}
                                </p>
                            </div>
                            @include('livewire.modification-requests._status-chip', ['status' => $req->status])
                        </div>
                    </div>

                    {{-- Body (scrollable) --}}
                    <div class="flex-1 overflow-y-auto min-h-0 px-6 py-5 space-y-5">

                        {{-- Parcel + Owner info --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-1">
                                    {{ __('modification_requests.col_parcel') }}
                                </p>
                                <p class="text-sm font-semibold text-on-surface dark:text-white ltr">
                                    {{ $req->parcel?->parcel_no ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-1">
                                    {{ __('modification_requests.col_owner') }}
                                </p>
                                <p class="text-sm text-on-surface dark:text-white">
                                    {{ $req->owner?->name ?? '—' }}
                                </p>
                            </div>
                        </div>

                        {{-- Field name --}}
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-widest
                                      text-on-surface-variant dark:text-on-primary-container mb-1">
                                {{ __('modification_requests.col_field') }}
                            </p>
                            <span class="text-sm font-medium text-secondary bg-secondary/10
                                         dark:bg-secondary/20 px-3 py-1 rounded-lg inline-block">
                                {{ $req->fieldLabel() }}
                            </span>
                        </div>

                        {{-- Old → New values --}}
                        <div class="grid grid-cols-1 gap-3">
                            <div class="flex items-start gap-3">
                                <div class="flex-1 rounded-xl bg-error/8 dark:bg-error/10
                                            border border-error/20 px-4 py-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-error mb-1">
                                        {{ __('modification_requests.current_value') }}
                                    </p>
                                    <p class="text-sm text-on-surface dark:text-white break-words">
                                        {{ $req->old_value ?? '—' }}
                                    </p>
                                </div>
                                <div class="flex items-center pt-5 shrink-0">
                                    <span class="material-symbols-outlined text-[22px]
                                                 text-on-surface-variant dark:text-on-primary-container"
                                          style="font-variation-settings: 'wght' 300">
                                        {{ app()->isLocale('ar') ? 'arrow_back' : 'arrow_forward' }}
                                    </span>
                                </div>
                                <div class="flex-1 rounded-xl bg-secondary/8 dark:bg-secondary/10
                                            border border-secondary/20 px-4 py-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-secondary mb-1">
                                        {{ __('modification_requests.requested_value') }}
                                    </p>
                                    <p class="text-sm font-semibold text-on-surface dark:text-white break-words">
                                        {{ $req->new_value ?? '—' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Existing notes --}}
                        @if ($req->notes)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-1">
                                    {{ __('modification_requests.notes_label') }}
                                </p>
                                <p class="text-sm text-on-surface dark:text-white/80 bg-surface-container
                                          dark:bg-[#252b3b] rounded-xl px-4 py-3 leading-relaxed">
                                    {{ $req->notes }}
                                </p>
                            </div>
                        @endif

                        {{-- Resolved at --}}
                        @if ($req->resolved_at)
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-1">
                                    {{ __('modification_requests.resolved_at') }}
                                </p>
                                <p class="text-sm text-on-surface dark:text-white ltr">
                                    {{ $req->resolved_at->format('Y-m-d H:i') }}
                                </p>
                            </div>
                        @endif

                        {{-- Manager actions — manage permission only, and only if transitions exist --}}
                        @can('modification_requests.manage')
                            @if (count($req->status->allowedTransitions()) > 0)
                                <div class="border-t border-outline-variant dark:border-white/10 pt-5">
                                    <p class="text-[11px] font-bold uppercase tracking-widest
                                              text-on-surface-variant dark:text-on-primary-container mb-3">
                                        {{ __('modification_requests.manager_note') }}
                                    </p>
                                    <textarea wire:model="managerNote"
                                              rows="2"
                                              placeholder="{{ __('modification_requests.manager_note_placeholder') }}"
                                              class="w-full px-3 py-2 text-sm rounded-xl resize-none
                                                     bg-surface-container dark:bg-[#252b3b]
                                                     border border-outline-variant dark:border-white/10
                                                     text-on-surface dark:text-white
                                                     focus:outline-none focus:ring-2 focus:ring-secondary/40
                                                     placeholder:text-on-surface-variant dark:placeholder:text-on-primary-container">
                                    </textarea>
                                </div>
                            @endif
                        @endcan

                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-outline-variant dark:border-white/10
                                flex flex-wrap items-center justify-between gap-3 shrink-0">

                        <button wire:click="closeModal"
                                class="px-4 py-2 text-sm rounded-xl
                                       border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('modification_requests.close') }}
                        </button>

                        @can('modification_requests.manage')
                            <div class="flex items-center gap-2">
                                @foreach ($req->status->allowedTransitions() as $next)
                                    @php
                                        $btnClass = match ($next) {
                                            \App\Enums\ModificationRequestStatus::SentToArcgis => 'bg-blue-600 hover:bg-blue-700 text-white',
                                            \App\Enums\ModificationRequestStatus::Applied       => 'bg-secondary hover:brightness-110 text-white',
                                            \App\Enums\ModificationRequestStatus::Rejected      => 'bg-error hover:brightness-110 text-white',
                                            default => 'bg-secondary text-white',
                                        };
                                        $btnIcon = match ($next) {
                                            \App\Enums\ModificationRequestStatus::SentToArcgis => 'send',
                                            \App\Enums\ModificationRequestStatus::Applied       => 'check_circle',
                                            \App\Enums\ModificationRequestStatus::Rejected      => 'cancel',
                                            default => 'update',
                                        };
                                        $btnLabel = match ($next) {
                                            \App\Enums\ModificationRequestStatus::SentToArcgis => __('modification_requests.action_send'),
                                            \App\Enums\ModificationRequestStatus::Applied       => __('modification_requests.action_apply'),
                                            \App\Enums\ModificationRequestStatus::Rejected      => __('modification_requests.action_reject'),
                                            default => $next->value,
                                        };
                                    @endphp
                                    <button wire:click="changeStatus('{{ $next->value }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="changeStatus"
                                            class="inline-flex items-center gap-1.5 px-4 py-2
                                                   text-sm font-semibold rounded-xl
                                                   disabled:opacity-60 transition-all {{ $btnClass }}">
                                        <span wire:loading wire:target="changeStatus"
                                              class="material-symbols-outlined text-[14px] animate-spin">
                                            progress_activity
                                        </span>
                                        <span class="material-symbols-outlined text-[16px]"
                                              wire:loading.remove wire:target="changeStatus">
                                            {{ $btnIcon }}
                                        </span>
                                        {{ $btnLabel }}
                                    </button>
                                @endforeach
                            </div>
                        @endcan

                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
