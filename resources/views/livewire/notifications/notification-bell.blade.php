<div class="relative" x-data="{ open: false }" wire:poll.15s="poll">

    <button @click="open = ! open" @click.outside="open = false"
            class="relative w-9 h-9 flex items-center justify-center rounded-xl
                   text-on-surface-variant dark:text-on-primary-container
                   hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
        <span class="material-symbols-outlined text-[20px]">notifications</span>

        <span class="absolute -top-0.5 -end-0.5 min-w-[16px] h-[16px] px-1
                     flex items-center justify-center rounded-full
                     bg-error text-white text-[10px] font-bold leading-none
                     {{ $this->count > 0 ? '' : 'opacity-0' }}">
            {{ $this->count > 9 ? '9+' : $this->count }}
        </span>
    </button>

    <div x-show="open" x-cloak
         class="absolute end-0 mt-2 w-80 z-50
                bg-surface-container-lowest dark:bg-[#1a1f2e]
                rounded-2xl border border-outline-variant dark:border-white/10
                shadow-2xl overflow-hidden">

        <div class="px-4 py-3 border-b border-outline-variant dark:border-white/10">
            <p class="text-sm font-semibold text-on-surface dark:text-white">
                {{ __('nav.notifications') }}
            </p>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-outline-variant/40 dark:divide-white/5">
            @forelse ($this->items as $item)
                <a href="{{ $item['url'] }}"
                   class="flex flex-col gap-0.5 px-4 py-3 text-start
                          hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    <span class="text-sm font-medium text-on-surface dark:text-white">
                        {{ $item['title'] }}
                    </span>
                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ $item['subtitle'] }}
                    </span>
                </a>
            @empty
                <p class="px-4 py-6 text-center text-sm
                          text-on-surface-variant dark:text-on-primary-container">
                    {{ __('nav.notifications_empty') }}
                </p>
            @endforelse
        </div>

    </div>

</div>
