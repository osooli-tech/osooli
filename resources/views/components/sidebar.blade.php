<aside class="fixed inset-y-0 start-0 w-[280px] bg-primary z-40 flex flex-col select-none">

    {{-- Logo — sidebar is always navy so we always use the dark-background version --}}
    <div class="flex items-center px-4 h-16 border-b border-white/10 shrink-0">
        <img src="{{ asset('images/logo2.jpeg') }}"
             alt="{{ __('nav.app_name') }}"
             class="h-10 w-auto object-contain">
    </div>

    {{-- Navigation --}}
    <nav class="flex-grow overflow-y-auto py-4 px-3 space-y-0.5" aria-label="{{ __('nav.main_nav') }}">
        @foreach ($navItems as $item)
            @if (is_null($item['permission']) || auth()->user()?->can($item['permission']))
                @php
                    $routeBase = rtrim($item['route'], '.index');
                    $isActive  = request()->routeIs($routeBase . '*');
                    $href      = Route::has($item['route']) ? route($item['route']) : '#';
                @endphp

                <a href="{{ $href }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                          {{ $isActive
                              ? 'bg-white/15 text-white border-e-[3px] border-tertiary-container'
                              : 'text-primary-fixed-dim hover:bg-white/8 hover:text-white' }}"
                   {{ $isActive ? 'aria-current=page' : '' }}>
                    <span class="material-symbols-outlined text-[22px] shrink-0"
                          style="font-variation-settings: 'FILL' {{ $isActive ? 1 : 0 }}, 'wght' 300, 'GRAD' 0, 'opsz' 24;">
                        {{ $item['icon'] }}
                    </span>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Divider --}}
    <div class="border-t border-white/10 mx-4"></div>

    {{-- Profile --}}
    <div class="px-4 py-4 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center shrink-0 text-white text-sm font-bold">
                {{ mb_substr(auth()->user()?->name ?? 'م', 0, 1) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-medium truncate">{{ auth()->user()?->name }}</p>
                <p class="text-primary-fixed-dim text-[11px] truncate">
                    {{ auth()->user()?->roles?->first()?->name ?? '' }}
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        class="text-primary-fixed-dim hover:text-white transition-colors"
                        title="{{ __('nav.logout') }}">
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                </button>
            </form>
        </div>
    </div>

</aside>
