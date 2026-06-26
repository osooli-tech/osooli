<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl shadow-sm overflow-hidden h-full">
    <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant dark:border-white/10">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-on-surface dark:text-white">
                {{ __('dashboard.recent_alerts') }}
            </h3>
            @if ($totalCount > 0)
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-error text-white text-[10px] font-bold data-tabular">
                    {{ $totalCount > 99 ? '99+' : $totalCount }}
                </span>
            @endif
        </div>
    </div>

    @if ($alerts->isEmpty())
        <div class="flex flex-col items-center justify-center py-10 gap-2 text-on-surface-variant dark:text-on-primary-container text-sm">
            <span class="material-symbols-outlined text-[32px] opacity-40">check_circle</span>
            <p>{{ __('dashboard.no_alerts') }}</p>
        </div>
    @else
        <ul class="divide-y divide-outline-variant dark:divide-white/5">
            @foreach ($alerts as $alert)
                <li class="flex items-start gap-3 px-5 py-3.5">
                    <span class="material-symbols-outlined text-[18px] text-error mt-0.5 shrink-0"
                          style="font-variation-settings: 'FILL' 1;">
                        warning
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-on-surface dark:text-white truncate">
                            {{ __('parcels.deed_no') }}: <span class="data-tabular">{{ $alert->deed_no ?? '—' }}</span>
                        </p>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">
                            {{ __('parcels.parcel_no') }}: {{ $alert->parcel?->parcel_no ?? '—' }}
                        </p>
                    </div>
                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-error-container text-error">
                        {{ __('parcels.deed_statuses.قديم') }}
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
