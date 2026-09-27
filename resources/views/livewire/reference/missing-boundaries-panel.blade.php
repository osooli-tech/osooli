<div>
    @if ($show)
        @php
            $drawable = collect($report['districts'] ?? [])->where('action', 'draw')->count();
            $total = collect($report)->flatten(1)->count();
        @endphp
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="close"></div>

            <div class="relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10 p-6 space-y-5">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[28px] text-primary dark:text-white shrink-0">border_clear</span>
                    <div class="space-y-1">
                        <p class="text-base font-bold text-on-surface dark:text-white">{{ __('boundaries.missing.title') }}</p>
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container leading-relaxed">{{ __('boundaries.missing.intro') }}</p>
                    </div>
                </div>

                @php
                    $solvable = collect($report)->flatten(1)->whereIn('action', ['draw', 'merge', 'move'])->count();
                @endphp
                @if ($solvable > 0 && $canMove)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-secondary/5 border border-secondary/30 px-4 py-3">
                        <p class="text-sm text-on-surface dark:text-white/90">{{ __('boundaries.missing.solve_hint', ['count' => $solvable]) }}</p>
                        <button type="button" wire:click="solveAll" wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium bg-secondary text-white hover:opacity-90 disabled:opacity-50">
                            <span class="material-symbols-outlined text-[18px]" wire:loading.class="animate-spin" wire:target="solveAll">auto_fix_high</span>
                            {{ __('boundaries.missing.solve_all') }}
                        </button>
                    </div>
                @endif

                @if ($total === 0)
                    <p class="text-sm rounded-xl bg-secondary/5 border border-secondary/30 px-4 py-3 text-on-surface dark:text-white/90">{{ __('boundaries.missing.none') }}</p>
                @endif

                @foreach (['districts', 'cities', 'regions'] as $level)
                    @continue(empty($report[$level]))
                    <section class="space-y-2" wire:key="missing-{{ $level }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-on-surface dark:text-white">
                                {{ __('boundaries.missing.levels.'.$level) }}
                                <span class="ms-1 px-2 py-0.5 rounded-full text-xs bg-surface-container dark:bg-white/10 data-tabular">{{ count($report[$level]) }}</span>
                            </h3>
                            @if ($level === 'districts' && $drawable > 1)
                                <button type="button" wire:click="drawAll" wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-sm font-medium bg-secondary text-white hover:opacity-90 disabled:opacity-50">
                                    <span class="material-symbols-outlined text-[18px]">draw</span>
                                    {{ __('boundaries.missing.draw_all', ['count' => $drawable]) }}
                                </button>
                            @endif
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-outline-variant dark:border-white/10">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-surface-container dark:bg-[#1e2435] text-on-surface-variant dark:text-on-primary-container">
                                        <th class="text-start px-3 py-2 font-semibold">{{ __('boundaries.missing.col_name') }}</th>
                                        <th class="text-start px-3 py-2 font-semibold">{{ __('boundaries.missing.col_parcels') }}</th>
                                        <th class="text-start px-3 py-2 font-semibold">{{ __('boundaries.missing.col_finding') }}</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                                    @foreach ($report[$level] as $row)
                                        <tr wire:key="missing-{{ $level }}-{{ $row['id'] }}">
                                            <td class="px-3 py-2">
                                                <span class="font-medium text-on-surface dark:text-white">{{ $row['name'] }}</span>
                                                @if ($row['parent'] !== '')
                                                    <span class="block text-xs text-on-surface-variant dark:text-on-primary-container">{{ $row['parent'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 data-tabular">{{ number_format($row['parcels']) }}</td>
                                            <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container">
                                                {{ __('boundaries.missing.findings.'.$level.'.'.$row['action'], ['target' => $row['target'], 'parent' => $row['parent'], 'count' => number_format($row['unlocated'])]) }}
                                            </td>
                                            <td class="px-3 py-2 text-end whitespace-nowrap">
                                                @if ($row['action'] === 'draw')
                                                    <button type="button" wire:click="draw({{ $row['id'] }})" wire:loading.attr="disabled"
                                                            class="px-3 py-1 rounded-lg text-xs font-medium bg-secondary text-white hover:opacity-90 disabled:opacity-50">
                                                        {{ __('boundaries.missing.draw') }}
                                                    </button>
                                                @elseif ($row['action'] === 'unused' && $canDelete)
                                                    <button type="button" wire:click="delete({{ $row['id'] }})" wire:loading.attr="disabled"
                                                            wire:confirm="{{ __('boundaries.missing.delete_confirm', ['name' => $row['name']]) }}"
                                                            class="px-3 py-1 rounded-lg text-xs font-medium text-error hover:bg-error/10 disabled:opacity-50">
                                                        {{ __('boundaries.missing.delete') }}
                                                    </button>
                                                @elseif (in_array($row['action'], ['merge', 'move'], true) && $canMove)
                                                    <button type="button" wire:click="move('{{ $level }}', {{ $row['id'] }})" wire:loading.attr="disabled"
                                                            class="px-3 py-1 rounded-lg text-xs font-medium bg-primary/10 text-primary hover:bg-primary/20 dark:bg-white/10 dark:text-white disabled:opacity-50">
                                                        {{ __('boundaries.missing.actions.'.$level.'.'.$row['action'], ['target' => $row['target']]) }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach

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
