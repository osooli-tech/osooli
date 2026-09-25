<div class="space-y-4">
    @php
        $card = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm';
    @endphp

    <div class="{{ $card }} p-5">
        <h1 class="text-lg font-bold text-on-surface dark:text-white">{{ __('placement.title') }}</h1>
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container mt-1">{{ __('placement.subtitle') }}</p>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['district', 'city'] as $tab)
                <button wire:click="show('{{ $tab }}')"
                        class="px-4 py-2 rounded-xl text-sm {{ $level === $tab ? 'bg-secondary text-white font-medium' : 'bg-surface-container dark:bg-white/5 text-on-surface-variant dark:text-on-primary-container' }}">
                    {{ __('placement.tabs.'.$tab) }}
                    <span class="ms-1 px-2 py-0.5 rounded-full text-xs {{ $level === $tab ? 'bg-white/20' : 'bg-surface dark:bg-white/10' }} data-tabular">{{ number_format($counts[$tab]) }}</span>
                </button>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('placement.hint.'.$level) }}</p>
    </div>

    <div class="{{ $card }} overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-surface-container dark:bg-[#1e2435] border-b border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container">
                        <th class="text-start px-4 py-3 font-semibold">{{ __('placement.col_parcel') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ __('placement.col_plan') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ __('placement.col_recorded') }}</th>
                        <th class="text-start px-4 py-3 font-semibold">{{ __('placement.col_actual') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($rows as $entry)
                        @php $row = $entry['row']; $actual = $entry['actual']; @endphp
                        <tr wire:key="placement-{{ $row->id }}" class="hover:bg-surface-container dark:hover:bg-white/5">
                            <td class="px-4 py-3 font-medium text-on-surface dark:text-white">
                                {{ $row->parcel_no ?? '—' }}
                                <span class="block text-xs text-on-surface-variant" dir="ltr">{{ $row->geo_id }}</span>
                            </td>
                            <td class="px-4 py-3 data-tabular">{{ $row->plan_no }}</td>
                            <td class="px-4 py-3">
                                {{ $level === 'district' ? $row->district.' — '.$row->city : $row->city }}
                                @if ($level === 'city' && $row->city_source === 'approximate')
                                    <span class="block text-xs text-amber-700 dark:text-amber-300">{{ __('placement.approximate') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-secondary font-medium">
                                {{ implode(' — ', array_filter([$actual['district'], $actual['city'], $actual['region']])) ?: __('geo_check.unknown_place') }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('parcels.show', $row->id) }}" class="inline-flex items-center gap-1 text-secondary hover:underline text-xs">
                                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                    {{ __('placement.open') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[40px] opacity-40 block">verified</span>
                                {{ __('placement.all_good') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($page->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">{{ $page->links() }}</div>
        @endif
    </div>
</div>
