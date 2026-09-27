<div>
    @if ($show && $report !== null)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="close"></div>

            <div class="relative z-10 w-full max-w-xl max-h-[90vh] overflow-y-auto bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10 p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[28px] text-primary dark:text-white shrink-0">my_location</span>
                    <div class="space-y-1">
                        <p class="text-base font-bold text-on-surface dark:text-white">{{ __('placement.plan.title', ['plan' => $report['plan']]) }}</p>
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('placement.plan.current', ['current' => $report['current']]) }}</p>
                    </div>
                </div>

                <div class="rounded-xl bg-surface-container dark:bg-white/5 p-4 space-y-2">
                    <p class="text-sm font-semibold text-on-surface dark:text-white">
                        {{ __('placement.plan.where', ['located' => number_format($report['located']), 'parcels' => number_format($report['parcels'])]) }}
                    </p>
                    @forelse ($report['spread'] as $place)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-on-surface dark:text-white/90">{{ $place['name'] }}</span>
                            <span class="data-tabular text-on-surface-variant dark:text-on-primary-container">{{ number_format($place['parcels']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('placement.plan.nowhere') }}</p>
                    @endforelse
                </div>

                @if ($report['options'] !== [])
                    @include('livewire.parcels.partials.fix-options', ['options' => $report['options']])
                @elseif ($report['located'] > 0)
                    <p class="text-sm rounded-xl bg-secondary/5 border border-secondary/30 text-on-surface dark:text-white/90 px-4 py-3">{{ __('placement.plan.no_move') }}</p>
                @endif

                <div class="flex justify-end">
                    <button type="button" wire:click="close"
                            class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5">
                        {{ __('placement.fix.close') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
