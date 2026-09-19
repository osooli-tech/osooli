<div class="space-y-4">

    {{-- Heading --}}
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container uppercase tracking-wide">
                {{ __('owners.ownership_section') }}
            </p>
            <p class="mt-0.5 text-[11px] text-on-surface-variant dark:text-on-primary-container/70">
                {{ __('owners.ownership_hint') }}
            </p>
        </div>
        @if (! $editing)
            <button type="button" wire:click="add"
                    class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg shrink-0
                           bg-secondary/10 text-secondary hover:bg-secondary/20 transition-colors">
                <span class="material-symbols-outlined text-[14px]">person_add</span>
                {{ __('owners.ownership_add') }}
            </button>
        @endif
    </div>

    {{-- Allocated vs. remaining: the ceiling the save refuses to cross, shown
         before the user reaches it rather than only as an error afterwards. --}}
    <div>
        <div class="flex items-center justify-between gap-3 text-xs mb-1.5">
            <span class="text-on-surface-variant dark:text-on-primary-container">
                {{ __('owners.ownership_allocated') }}
                <span class="font-semibold text-on-surface dark:text-white data-tabular">{{ number_format($allocated, 2) }}٪</span>
            </span>
            <span class="text-on-surface-variant dark:text-on-primary-container">
                {{ __('owners.ownership_remaining') }}
                <span class="font-semibold text-secondary data-tabular">{{ number_format($remaining, 2) }}٪</span>
            </span>
        </div>
        <div class="h-1.5 rounded-full overflow-hidden bg-outline-variant/40 dark:bg-white/10">
            <div class="h-full rounded-full bg-secondary transition-all" style="width: {{ min(100, $allocated) }}%"></div>
        </div>
    </div>

    @error('form.conflict')
        <p class="px-3 py-2 text-xs rounded-xl bg-error/10 text-error">{{ $message }}</p>
    @enderror

    {{-- Link / edit form --}}
    @if ($editing)
        <form wire:submit="save"
              class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-xl
                     bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10">

            {{-- Owner picker: the search box narrows the list the dropdown
                 offers, so a person is chosen by name and the id only ever
                 travels as the value of the option they picked. --}}
            <div>
                <label for="ownership-owner-search" class="sr-only">{{ __('owners.ownership_owner_search') }}</label>
                <input type="search" id="ownership-owner-search"
                       wire:model.live.debounce.400ms="ownerSearch"
                       autocomplete="off"
                       placeholder="{{ __('owners.ownership_owner_search') }}"
                       class="w-full mb-2 px-3 py-2 text-sm rounded-xl
                              bg-surface-container dark:bg-[#252b3b]
                              border border-outline-variant dark:border-white/10
                              text-on-surface dark:text-white
                              focus:outline-none focus:ring-2 focus:ring-primary/40">

                <x-form.select name="form.ownerId"
                               :label="__('owners.owner')"
                               :options="$ownerOptions"
                               required />

                @if ($ownerOptions === [])
                    <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">
                        {{ __('owners.ownership_owner_empty') }}
                    </p>
                @elseif (count($ownerOptions) >= $pickerLimit)
                    <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">
                        {{ __('owners.ownership_picker_limit', ['count' => $pickerLimit]) }}
                    </p>
                @endif
            </div>

            {{-- Text, not number: a browser hands back an empty string for input
                 it dislikes, and empty means "written as prose in the deed" here
                 — a typo must not turn into that. --}}
            <x-form.input name="form.share"
                          :label="__('owners.ownership_share')"
                          :hint="__('owners.ownership_share_hint')"
                          inputmode="decimal"
                          placeholder="{{ __('owners.ownership_share_placeholder') }}"
                          ltr />

            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                               bg-secondary text-white hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-[14px]">check</span>
                    {{ __('common.save') }}
                </button>
                <button type="button" wire:click="cancel"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                               border border-outline-variant dark:border-white/10
                               text-on-surface-variant dark:text-on-primary-container
                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    {{ __('common.cancel') }}
                </button>
            </div>
        </form>
    @endif

    {{-- Current owners of this deed --}}
    <div class="overflow-x-auto rounded-xl border border-outline-variant dark:border-white/10">
        <table class="w-full text-xs">
            <thead>
                <tr class="bg-surface dark:bg-[#1a2435] border-b border-outline-variant dark:border-white/10">
                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('owners.name') }}</th>
                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('owners.national_id') }}</th>
                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('owners.ownership_share') }}</th>
                    <th class="px-3 py-2"><span class="sr-only">{{ __('common.edit') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant dark:divide-white/5">
                @forelse ($links as $link)
                    <tr wire:key="deed-owner-{{ $link->id }}"
                        class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                        <td class="px-3 py-2 font-medium text-on-surface dark:text-white">
                            {{ $link->owner?->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container data-tabular" dir="ltr">
                            {{ $link->owner?->national_id ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            @if ($link->ownership_share === null)
                                {{-- Not "0%": the deed states this share in words. --}}
                                <span class="text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('owners.ownership_share_prose') }}
                                </span>
                            @else
                                <span class="font-semibold text-secondary data-tabular">
                                    {{ number_format((float) $link->ownership_share, 2) }}٪
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" wire:click="edit({{ $link->id }})"
                                        title="{{ __('owners.ownership_edit') }}"
                                        class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button type="button" wire:click="unlink({{ $link->id }})"
                                        wire:confirm="{{ __('owners.ownership_confirm_unlink') }}"
                                        title="{{ __('owners.ownership_unlink') }}"
                                        class="text-on-surface-variant dark:text-on-primary-container hover:text-error transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">link_off</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.ownership_none') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
