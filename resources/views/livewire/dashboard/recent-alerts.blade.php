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
        {{-- The full set behind the count: the parcel list filtered to old deeds --}}
        @if ($totalCount > 0)
            @can('parcels.view')
                <a href="{{ route('parcels.index', ['deed_status' => \App\Enums\DeedStatus::Old->value]) }}"
                   class="text-xs text-secondary hover:underline font-medium">
                    {{ __('dashboard.view_all') }}
                </a>
            @endcan
        @endif
    </div>

    @if ($alerts->isEmpty())
        <div class="flex flex-col items-center justify-center py-10 gap-2 text-on-surface-variant dark:text-on-primary-container text-sm">
            <span class="material-symbols-outlined text-[32px] opacity-40">check_circle</span>
            <p>{{ __('dashboard.no_alerts') }}</p>
        </div>
    @else
        <ul class="divide-y divide-outline-variant dark:divide-white/5">
            @foreach ($alerts as $alert)
                <li class="relative flex items-start gap-3 px-5 py-3.5
                           {{ $alert->parcel ? 'hover:bg-surface-container dark:hover:bg-white/5 transition-colors' : '' }}">
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
                    @if ($alert->parcel)
                        {{-- Covers the whole item, so a click anywhere on it opens the parcel --}}
                        <a href="{{ route('parcels.show', $alert->parcel) }}"
                           class="absolute inset-0"
                           aria-label="{{ __('parcels.deed_no') }}: {{ $alert->deed_no ?? '—' }}"></a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
