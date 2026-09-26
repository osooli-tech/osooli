<div class="space-y-4">

    {{-- Tabs --}}
    <div class="flex flex-wrap gap-2">
        @foreach (['parcels' => __('archive.parcels'), 'deeds' => __('archive.deeds'), 'owners' => __('archive.owners'), 'values' => __('archive.values')] as $key => $label)
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

    {{-- What this tab holds, in a sentence or two. --}}
    <div class="flex items-start gap-2 rounded-xl bg-primary/5 dark:bg-white/5 px-4 py-3 text-sm text-on-surface dark:text-white">
        <span class="material-symbols-outlined text-[20px] text-primary dark:text-white/80 shrink-0">info</span>
        <p>{{ __('archive.explain.'.$tab) }}</p>
    </div>

    <div class="flex flex-wrap gap-3 items-end">
        @if ($tab === 'values')
            <select wire:model.live="category"
                    class="px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b]
                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white">
                <option value="">{{ __('archive.categories.all') }}</option>
                @foreach (array_keys(\App\Support\ArchivedValues::CATEGORIES) as $key)
                    <option value="{{ $key }}">{{ __('archive.categories.'.$key) }}</option>
                @endforeach
            </select>
        @endif
        <input wire:model.live.debounce.400ms="search" type="search"
               placeholder="{{ __('common.search') }}"
               class="w-full md:w-80 px-3 py-2 text-sm rounded-xl
                      bg-surface-container dark:bg-[#252b3b]
                      border border-outline-variant dark:border-white/10
                      text-on-surface dark:text-white
                      focus:outline-none focus:ring-2 focus:ring-primary/40" />

        <x-table.created-filter />

        @if ($this->filteringByCreatedAt())
            <button wire:click="clearCreatedAtFilter"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                           text-error border border-error/30 hover:bg-error/10 transition-colors">
                <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                {{ __('common.clear') }}
            </button>
        @endif
    </div>

    <div class="bg-surface dark:bg-[#1b2030] rounded-2xl border border-outline-variant dark:border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-container dark:bg-[#252b3b]">
                    <tr>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('archive.record') }}
                        </th>
                        <x-table.created-header :sort="$createdSort" />
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
                                @elseif ($tab === 'values')
                                    @php $d = $described[$record->id]; @endphp
                                    <p class="font-medium">
                                        {{ $d['record'] }}
                                        <span class="text-on-surface-variant dark:text-on-primary-container font-normal">— {{ $d['field'] }}</span>
                                        @if ($d['url'])
                                            <a href="{{ $d['url'] }}" class="ms-1 inline-flex items-center text-xs text-secondary hover:underline">
                                                <span class="material-symbols-outlined text-[14px]">open_in_new</span>{{ __('archive.open_parcel') }}
                                            </a>
                                        @endif
                                    </p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm">
                                        <span class="text-xs text-on-surface-variant">{{ __('archive.before') }}</span>
                                        <span class="rounded bg-error/10 px-1.5 text-error" dir="auto">{{ $d['before'] }}</span>
                                        <span class="material-symbols-outlined text-[16px] text-on-surface-variant rtl:rotate-180">arrow_forward</span>
                                        <span class="text-xs text-on-surface-variant">{{ __('archive.now') }}</span>
                                        <span class="rounded bg-secondary/10 px-1.5 text-secondary" dir="auto">{{ $d['now'] }}</span>
                                    </p>
                                    <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">
                                        {{ __('archive.reason') }}: {{ $record->reason }}
                                    </p>
                                @else
                                    {{ $record->name }}
                                @endif
                            </td>
                            <x-table.created-cell :date="$record->created_at" />
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ ($tab === 'values' ? $record->created_at : $record->deleted_at)?->format('Y-m-d H:i') ?? '—' }}
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
                                                {{ $tab === 'values' ? __('archive.confirm_restore_value') : __('archive.confirm_restore') }}
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
                            <td colspan="5" class="px-4 py-16 text-center text-on-surface-variant dark:text-on-primary-container">
                                {{ __('archive.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
