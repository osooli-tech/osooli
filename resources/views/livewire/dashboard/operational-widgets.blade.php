<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

    {{-- Pending modification requests --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm
                border border-outline-variant dark:border-white/10 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-error-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px] text-error"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                edit_note
            </span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container leading-tight mb-1">
                {{ __('dashboard.pending_mod_requests') }}
            </p>
            <p class="text-2xl font-bold text-on-surface dark:text-white data-tabular">
                {{ number_format($pendingModRequests) }}
            </p>
            @if ($pendingModRequests > 0)
                <p class="text-xs text-error mt-1">{{ __('dashboard.needs_action') }}</p>
            @else
                <p class="text-xs text-secondary mt-1">{{ __('dashboard.all_clear') }}</p>
            @endif
        </div>
    </div>

    {{-- Last sync --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm
                border border-outline-variant dark:border-white/10 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px] text-secondary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                sync
            </span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container leading-tight mb-1">
                {{ __('dashboard.last_sync') }}
            </p>
            @if ($lastSyncHuman === null)
                <p class="text-sm font-semibold text-on-surface-variant dark:text-on-primary-container">
                    {{ __('dashboard.never') }}
                </p>
            @else
                <p class="text-sm font-bold text-on-surface dark:text-white">{{ $lastSyncHuman }}</p>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    @php
                        $statusColor = match ($lastSyncStatus) {
                            'success' => 'text-secondary',
                            'failed'  => 'text-error',
                            default   => 'text-tertiary-container',
                        };
                    @endphp
                    <span class="text-xs font-medium {{ $statusColor }}">
                        {{ __('dashboard.sync_status_'.$lastSyncStatus) }}
                    </span>
                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ number_format($lastSyncImported) }} {{ __('dashboard.records_imported') }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- Active users --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm
                border border-outline-variant dark:border-white/10 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-primary-container flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px] text-primary"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                manage_accounts
            </span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container leading-tight mb-1">
                {{ __('dashboard.active_users') }}
            </p>
            <p class="text-2xl font-bold text-on-surface dark:text-white data-tabular">
                {{ number_format($activeUsers) }}
            </p>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-1">
                {{ __('dashboard.active_label') }}
            </p>
        </div>
    </div>

</div>
