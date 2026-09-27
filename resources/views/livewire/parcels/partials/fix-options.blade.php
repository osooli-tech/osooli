{{-- The moves PlacementFix offers, one card each with its own «تطبيق».
     Expects $options; the component answers applyFix(kind, targetId). --}}
@php
    // The option whose target holds the largest share of the parcels it moves.
    $share = fn ($o) => $o['located'] > 0 ? $o['inside'] / $o['located'] : 0;
    $ranked = collect($options)->sortByDesc($share);
    $best = $ranked->keys()->first();
    // No suggestion when two options settle the same share: the choice is the user's.
    if ($ranked->count() > 1 && $share($ranked->values()[0]) === $share($ranked->values()[1])) {
        $best = null;
    }
@endphp

@forelse ($options as $i => $option)
    @php $rest = $option['located'] - $option['inside']; @endphp
    <div wire:key="fix-{{ $option['kind'] }}-{{ $option['target_id'] }}"
         class="rounded-xl border p-4 space-y-2 {{ $i === $best ? 'border-secondary bg-secondary/5' : 'border-outline-variant dark:border-white/10' }}">
        <div class="flex items-start justify-between gap-3">
            <p class="font-semibold text-on-surface dark:text-white">
                {{ __('placement.fix.'.$option['kind'], ['subject' => $option['subject'], 'to' => $option['to']]) }}
            </p>
            @if ($i === $best && count($options) > 1)
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
