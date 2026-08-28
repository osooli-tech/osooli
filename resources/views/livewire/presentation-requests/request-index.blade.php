<div>

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ $requests->total() }} {{ trans_choice('presentation_requests.total', $requests->total()) }}
        </p>

        {{-- Search --}}
        <div class="relative w-full sm:w-72">
            <span class="absolute inset-y-0 end-3 flex items-center pointer-events-none
                         text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </span>
            <input wire:model.live.debounce.300ms="search"
                   type="search"
                   placeholder="{{ __('presentation_requests.search_placeholder') }}"
                   class="w-full pe-10 ps-4 py-2 text-sm rounded-xl
                          bg-surface-container dark:bg-[#252b3b]
                          border border-outline-variant dark:border-white/10
                          text-on-surface dark:text-white
                          focus:outline-none focus:ring-2 focus:ring-secondary/40" />
        </div>
    </div>

    {{-- ── Table ── --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e]
                rounded-2xl border border-outline-variant dark:border-white/10
                shadow-sm overflow-hidden">

        @if ($requests->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 gap-3
                        text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[48px] opacity-30">connect_without_contact</span>
                <p class="text-sm">{{ __('presentation_requests.empty') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant dark:border-white/10
                                   text-[11px] font-bold uppercase tracking-wider
                                   text-on-surface-variant dark:text-on-primary-container">
                            <th class="px-5 py-3 text-start">{{ __('presentation_requests.col_name') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('presentation_requests.col_phone') }}</th>
                            <th class="px-5 py-3 text-start hidden md:table-cell">{{ __('presentation_requests.col_message') }}</th>
                            <th class="px-5 py-3 text-start hidden lg:table-cell">{{ __('presentation_requests.col_date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 dark:divide-white/5">
                        @foreach ($requests as $req)
                            <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                <td class="px-5 py-3.5 font-semibold text-on-surface dark:text-white">
                                    {{ $req->name }}
                                </td>
                                <td class="px-5 py-3.5 text-on-surface dark:text-white/80 ltr">
                                    {{ $req->phone }}
                                </td>
                                <td class="px-5 py-3.5 hidden md:table-cell
                                           text-on-surface-variant dark:text-on-primary-container
                                           max-w-[280px] truncate">
                                    {{ $req->message ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 hidden lg:table-cell
                                           text-on-surface-variant dark:text-on-primary-container text-xs ltr">
                                    {{ $req->created_at->format('Y-m-d H:i') }}
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

</div>
