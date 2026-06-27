<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-on-surface dark:text-white">{{ __('audit_logs.title') }}</h1>
        <p class="text-sm text-on-surface-variant mt-1">{{ __('audit_logs.subtitle') }}</p>
    </div>

    {{-- Filters --}}
    <div class="bg-surface-container-low dark:bg-[#1a2e42] rounded-2xl p-4 mb-5 flex flex-wrap gap-3 items-center">

        {{-- Search --}}
        <div class="relative flex-1 min-w-[200px]">
            <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-on-surface-variant text-xl pointer-events-none">search</span>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('audit_logs.search_placeholder') }}"
                class="w-full ps-10 pe-4 py-2 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-white dark:bg-[#0f2235] text-on-surface dark:text-white placeholder-on-surface-variant text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
        </div>

        {{-- Action filter --}}
        <select
            wire:model.live="actionFilter"
            class="rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-white dark:bg-[#0f2235] text-on-surface dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary"
        >
            <option value="all">{{ __('audit_logs.filter_all') }}</option>
            @foreach($actionOptions as $act)
                <option value="{{ $act }}">{{ __('audit_logs.actions.'.$act, ['default' => $act]) }}</option>
            @endforeach
        </select>

    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-[#0f2235] rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-container dark:bg-[#1a2e42] text-on-surface-variant text-xs uppercase tracking-wider">
                        <th class="px-4 py-3 text-start font-medium">{{ __('audit_logs.col_user') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('audit_logs.col_action') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('audit_logs.col_target') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('audit_logs.col_ip') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('audit_logs.col_date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container dark:divide-[#1a2e42]">
                    @forelse($logs as $log)
                        <tr class="hover:bg-surface-container-low dark:hover:bg-[#162840] transition-colors">

                            {{-- User --}}
                            <td class="px-4 py-3">
                                @if($log->user)
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-primary-container flex items-center justify-center text-xs font-bold text-on-primary-container shrink-0">
                                            {{ mb_substr($log->user->name, 0, 1) }}
                                        </div>
                                        <span class="text-on-surface dark:text-white">{{ $log->user->name }}</span>
                                    </div>
                                @else
                                    <span class="text-on-surface-variant italic">{{ __('audit_logs.unknown_user') }}</span>
                                @endif
                            </td>

                            {{-- Action chip --}}
                            <td class="px-4 py-3">
                                @php
                                    $chip = match($log->action) {
                                        'login'    => ['bg-secondary-container text-on-secondary-container', 'login'],
                                        'logout'   => ['bg-surface-container text-on-surface-variant', 'logout'],
                                        'download' => ['bg-primary-fixed text-on-primary-fixed', 'download'],
                                        'export'   => ['bg-tertiary-container text-tertiary', 'table_chart'],
                                        default    => ['bg-surface-container-high text-on-surface-variant', 'info'],
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $chip[0] }}">
                                    <span class="material-symbols-outlined text-xs">{{ $chip[1] }}</span>
                                    {{ __('audit_logs.actions.'.$log->action, ['default' => $log->action]) }}
                                </span>
                            </td>

                            {{-- Target --}}
                            <td class="px-4 py-3 text-on-surface-variant">
                                @if($log->target_type && $log->target_id)
                                    <span class="font-mono text-xs bg-surface-container dark:bg-[#1a2e42] px-2 py-0.5 rounded">
                                        {{ $log->target_type }}#{{ $log->target_id }}
                                    </span>
                                @else
                                    {{ __('audit_logs.no_target') }}
                                @endif
                            </td>

                            {{-- IP --}}
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-on-surface-variant" dir="ltr">{{ $log->ip_address ?? '—' }}</span>
                            </td>

                            {{-- Date --}}
                            <td class="px-4 py-3 text-on-surface-variant text-xs whitespace-nowrap">
                                {{ $log->created_at?->format('Y-m-d H:i') }}
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <span class="material-symbols-outlined text-4xl text-on-surface-variant block mb-2">list_alt</span>
                                <p class="text-on-surface-variant">
                                    {{ $search || $actionFilter !== 'all' ? __('audit_logs.empty_filtered') : __('audit_logs.empty') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-surface-container dark:border-[#1a2e42]">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
