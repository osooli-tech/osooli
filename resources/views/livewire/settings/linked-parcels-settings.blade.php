<div class="bg-white dark:bg-[#0b1a2b] rounded-2xl shadow-sm p-5 mb-6">
    <div class="flex items-start gap-3 mb-4">
        <span class="material-symbols-outlined text-secondary text-[28px]">family_restroom</span>
        <div>
            <h2 class="font-semibold text-on-surface dark:text-white">{{ __('settings.linked.title') }}</h2>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5 max-w-2xl">{{ __('settings.linked.hint') }}</p>
        </div>
    </div>

    <ul class="grid grid-cols-1 md:grid-cols-3 gap-3">
        {{-- Always on: without it there is nothing to show at all --}}
        <li class="md:col-span-3 flex items-center gap-3 rounded-xl px-4 py-3 bg-surface-container dark:bg-white/5 text-sm">
            <span class="material-symbols-outlined text-[20px] text-secondary">check_circle</span>
            {{ __('settings.linked.always') }}
        </li>

        @foreach ($options as $key)
            <li>
                <label class="flex items-start gap-3 h-full rounded-xl px-4 py-3 border border-outline-variant dark:border-white/10 cursor-pointer
                              hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    <input type="checkbox" wire:model="switches.{{ $key }}"
                           class="mt-0.5 rounded border-outline text-secondary focus:ring-secondary">
                    <span>
                        <span class="block text-sm font-medium text-on-surface dark:text-white">{{ __('settings.linked.'.$key) }}</span>
                        <span class="block text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">{{ __('settings.linked.'.$key.'_hint') }}</span>
                    </span>
                </label>
            </li>
        @endforeach
    </ul>

    <div class="flex justify-end mt-4">
        <button type="button" wire:click="save"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-medium bg-secondary text-white hover:opacity-90">
            <span class="material-symbols-outlined text-[18px]">save</span>{{ __('settings.linked.save') }}
        </button>
    </div>
</div>
