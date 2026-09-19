<div class="space-y-4">

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2">
        @foreach (['parcels' => __('archive.parcels'), 'deeds' => __('archive.deeds'), 'owners' => __('archive.owners')] as $key => $label)
            <button type="button" wire:click="switchTab('{{ $key }}')"
                    class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl transition
                           {{ $tab === $key
                               ? 'bg-primary text-white'
                               : 'bg-surface-container dark:bg-[#252b3b] text-on-surface-variant dark:text-on-primary-container' }}">
                {{ $label }}
                <span class="px-1.5 py-0.5 rounded-full text-xs
                             {{ $tab === $key ? 'bg-white/20' : 'bg-outline-variant/40 dark:bg-white/10' }}">
                    {{ $counts[$key] }}
                </span>
            </button>
        @endforeach
    </div>

    <input wire:model.live.debounce.400ms="search" type="search"
           placeholder="{{ __('common.search') }}"
           class="w-full md:w-80 px-3 py-2 text-sm rounded-xl
                  bg-surface-container dark:bg-[#252b3b]
                  border border-outline-variant dark:border-white/10
                  text-on-surface dark:text-white
                  focus:outline-none focus:ring-2 focus:ring-primary/40" />

    <div class="bg-surface dark:bg-[#1b2030] rounded-2xl border border-outline-variant dark:border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-container dark:bg-[#252b3b]">
                    <tr>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('archive.record') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('archive.archived_at') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('archive.archived_by') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="border-t border-outline-variant/60 dark:border-white/5">
                            <td class="px-4 py-3 text-on-surface dark:text-white">
                                @if ($tab === 'parcels')
                                    {{ $record->parcel_no ?: '—' }}
                                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container/70">
                                        ({{ $record->geo_id }})
                                    </span>
                                @elseif ($tab === 'deeds')
                                    {{ $record->deed_no ?: '—' }}
                                @else
                                    {{ $record->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $record->deleted_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $record->archivedBy?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if ($canRestore)
                                    @if ($restoringId === $record->id)
                                        {{-- Confirmation is inline rather than a dialog: restoring
                                             puts a record back into every list and report at once,
                                             so the click deserves a deliberate second step. --}}
                                        <div class="flex items-center gap-2 justify-end">
                                            <span class="text-xs text-on-surface-variant dark:text-on-primary-container">
                                                {{ __('archive.confirm_restore') }}
                                            </span>
                                            <button type="button" wire:click="restore({{ $record->id }})"
                                                    class="px-3 py-1.5 text-xs rounded-lg bg-primary text-white">
                                                {{ __('common.restore') }}
                                            </button>
                                            <button type="button" wire:click="cancelRestore"
                                                    class="px-3 py-1.5 text-xs rounded-lg
                                                           bg-surface-container dark:bg-[#252b3b]
                                                           text-on-surface-variant dark:text-on-primary-container">
                                                {{ __('common.cancel') }}
                                            </button>
                                        </div>
                                    @else
                                        <button type="button" wire:click="confirmRestore({{ $record->id }})"
                                                class="px-3 py-1.5 text-xs rounded-lg
                                                       bg-surface-container dark:bg-[#252b3b]
                                                       text-on-surface dark:text-white
                                                       hover:bg-primary/10 transition">
                                            {{ __('common.restore') }}
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center text-on-surface-variant dark:text-on-primary-container">
                                {{ __('archive.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
