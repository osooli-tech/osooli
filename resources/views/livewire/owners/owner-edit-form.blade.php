<div class="mb-5 pb-4 border-b border-outline-variant dark:border-white/10">

    <div class="flex items-center justify-between mb-2">
        <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container uppercase tracking-wide">
            {{ __('owners.owner_data') }}
        </p>
        @if (! $editing)
            <button type="button" wire:click="edit"
                    class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg
                           bg-secondary/10 text-secondary hover:bg-secondary/20 transition-colors">
                <span class="material-symbols-outlined text-[14px]">edit</span>
                {{ __('owners.edit_owner') }}
            </button>
        @endif
    </div>

    @if (! $editing)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div>
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('owners.name') }}</p>
                <p class="font-medium text-on-surface dark:text-white">{{ $owner->name }}</p>
            </div>
            <div>
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('owners.national_id') }}</p>
                <p class="font-medium text-on-surface dark:text-white data-tabular" dir="ltr">{{ $owner->national_id ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('owners.phone') }}</p>
                <p class="font-medium text-on-surface dark:text-white data-tabular" dir="ltr">{{ $owner->phone ?? '—' }}</p>
            </div>
        </div>
    @else
        <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('owners.name') }}
                </label>
                <input type="text" wire:model="name"
                       class="w-full px-2.5 py-1.5 text-sm rounded-lg
                              bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10
                              text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40">
                @error('name') <p class="text-[11px] text-error mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('owners.national_id') }}
                </label>
                <input type="text" wire:model="nationalId" dir="ltr"
                       class="w-full px-2.5 py-1.5 text-sm rounded-lg data-tabular
                              bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10
                              text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40">
                @error('nationalId') <p class="text-[11px] text-error mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[11px] text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('owners.phone') }}
                </label>
                <input type="text" wire:model="phone" dir="ltr"
                       class="w-full px-2.5 py-1.5 text-sm rounded-lg data-tabular
                              bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10
                              text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40">
                @error('phone') <p class="text-[11px] text-error mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3 flex items-center gap-2">
                <button type="submit"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                               bg-secondary text-white hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-[14px]">check</span>
                    {{ __('owners.save') }}
                </button>
                <button type="button" wire:click="cancel"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg
                               border border-outline-variant dark:border-white/10
                               text-on-surface-variant dark:text-on-primary-container
                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    {{ __('owners.cancel') }}
                </button>
            </div>
        </form>
    @endif

</div>
