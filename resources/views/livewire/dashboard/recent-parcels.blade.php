<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant dark:border-white/10">
        <h3 class="text-sm font-semibold text-on-surface dark:text-white">
            {{ __('dashboard.recent_parcels') }}
        </h3>
        <a href="{{ route('dashboard') }}"
           class="text-xs text-secondary hover:underline font-medium">
            {{ __('dashboard.view_all') }}
        </a>
    </div>

    @if ($parcels->isEmpty())
        <div class="flex items-center justify-center py-12 text-on-surface-variant dark:text-on-primary-container text-sm">
            {{ __('dashboard.no_data') }}
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10 text-xs text-on-surface-variant dark:text-on-primary-container">
                        <th class="text-start px-5 py-3 font-medium">{{ __('parcels.parcel_no') }}</th>
                        <th class="text-start px-3 py-3 font-medium">{{ __('parcels.asset_type') }}</th>
                        <th class="text-start px-3 py-3 font-medium">{{ __('parcels.deed_no') }}</th>
                        <th class="text-start px-3 py-3 font-medium">{{ __('parcels.deed_date') }}</th>
                        <th class="text-start px-3 py-3 font-medium">{{ __('parcels.deed_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/5">
                    @foreach ($parcels as $parcel)
                        @php $latestDeed = $parcel->deeds->sortByDesc('id')->first(); @endphp
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <td class="px-5 py-3 font-medium text-on-surface dark:text-white data-tabular">
                                {{ $parcel->parcel_no ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $parcel->asset_type ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $latestDeed?->deed_no ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $latestDeed?->deed_date_hijri ?? '—' }}
                            </td>
                            <td class="px-3 py-3">
                                @if ($latestDeed?->deed_status === 'محدث')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-secondary/15 text-secondary">
                                        <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                                        {{ __('parcels.deed_statuses.محدث') }}
                                    </span>
                                @elseif ($latestDeed?->deed_status === 'قديم')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-error-container text-error">
                                        <span class="material-symbols-outlined text-[12px]" style="font-variation-settings: 'FILL' 1;">warning</span>
                                        {{ __('parcels.deed_statuses.قديم') }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
