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
                            <td class="px-4 py-3 text-end whitespace-nowrap">
                                <div class="inline-flex items-center gap-3">
                                    @if ($canFix)
                                        <button type="button" wire:click="openFix({{ $row->id }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-primary/10 text-primary hover:bg-primary/20 dark:bg-white/10 dark:text-white dark:hover:bg-white/20">
                                            <span class="material-symbols-outlined text-[16px]">edit_location_alt</span>
                                            {{ __('placement.fix.button') }}
                                        </button>
                                    @endif
                                    <a href="{{ route('parcels.show', $row->id) }}" class="inline-flex items-center gap-1 text-secondary hover:underline text-xs">
                                        <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                        {{ __('placement.open') }}
                                    </a>
                                </div>
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
    @if ($fixing !== null)
        @php
            // The option whose target holds the largest share of the parcels it moves.
            $best = collect($fixOptions)->sortByDesc(fn ($o) => $o['located'] > 0 ? $o['inside'] / $o['located'] : 0)->keys()->first();
        @endphp
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeFix()">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeFix"></div>

            <div class="relative z-10 w-full max-w-xl max-h-[90vh] overflow-y-auto bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10 p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[28px] text-primary dark:text-white shrink-0">edit_location_alt</span>
                    <div class="space-y-1">
                        <p class="text-base font-bold text-on-surface dark:text-white">{{ __('placement.fix.title') }}</p>
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container leading-relaxed">{{ __('placement.fix.why') }}</p>
                    </div>
                </div>

                @forelse ($fixOptions as $i => $option)
                    @php $rest = $option['located'] - $option['inside']; @endphp
                    <div wire:key="fix-{{ $option['kind'] }}-{{ $option['target_id'] }}"
                         class="rounded-xl border p-4 space-y-2 {{ $i === $best ? 'border-secondary bg-secondary/5' : 'border-outline-variant dark:border-white/10' }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-on-surface dark:text-white">
                                {{ __('placement.fix.'.$option['kind'], ['subject' => $option['subject'], 'to' => $option['to']]) }}
                            </p>
                            @if ($i === $best && count($fixOptions) > 1)
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-xs bg-secondary text-white">{{ __('placement.fix.recommended') }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('placement.fix.from_'.$option['kind'], ['from' => $option['from']]) }}</p>
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
                            {{ __('placement.fix.scope_'.$option['kind'], ['parcels' => number_format($option['parcels'])]) }}
                            {{ __('placement.fix.inside', ['inside' => number_format($option['inside']), 'located' => number_format($option['located']), 'to' => $option['to']]) }}
                        </p>
                        @if ($rest > 0)
                            <p class="text-xs text-amber-700 dark:text-amber-300 flex items-start gap-1">
                                <span class="material-symbols-outlined text-[16px]">warning</span>
                                {{ __('placement.fix.rest', ['count' => number_format($rest)]) }}
                            </p>
                        @endif
                        <div class="flex justify-end">
                            <button type="button" wire:click="applyFix('{{ $option['kind'] }}', {{ $option['target_id'] }})"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 text-sm rounded-xl bg-secondary text-white font-medium hover:opacity-90 disabled:opacity-50">
                                {{ __('placement.fix.apply') }}
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 px-4 py-3">{{ __('placement.fix.none') }}</p>
                @endforelse

                <div class="flex justify-end">
                    <button type="button" wire:click="closeFix"
                            class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5">
                        {{ __('placement.fix.close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
