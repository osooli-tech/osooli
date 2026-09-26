{{-- Slides only once toggled — see sidebarAnimate in layouts/app.blade.php --}}
<aside class="fixed inset-y-0 start-0 w-[280px] bg-primary z-50 flex flex-col select-none shadow-2xl lg:shadow-none"
       :class="{ 'translate-x-0': sidebarOpen, 'rtl:translate-x-full ltr:-translate-x-full': ! sidebarOpen, 'transition-transform duration-300 ease-in-out': sidebarAnimate }">

    {{-- Logo — sidebar is always navy so the transparent-background mark is used directly --}}
    <div class="flex items-center gap-2 px-4 h-16 border-b border-white/10 shrink-0">
        <img src="{{ asset('images/logo-icon-sm.png') }}"
             alt="{{ __('nav.app_name') }}"
             class="h-10 w-auto object-contain">
        <span class="text-white font-bold text-lg">{{ __('nav.app_name') }}</span>
    </div>

    {{-- Navigation --}}
    <nav class="flex-grow overflow-y-auto py-4 px-3 space-y-0.5" aria-label="{{ __('nav.main_nav') }}">
        @php
            $user = auth()->user();
            // Every entry across the groups — the active check below needs to
            // know whether the current page has an entry of its own.
            $allRoutes = collect($navGroups)->pluck('items')->flatten(1)->pluck('route');
            $current = request()->route()?->getName();
        @endphp

        @foreach ($navGroups as $group)
            @php
                $visible = array_filter($group['items'],
                    fn ($item) => is_null($item['permission']) || $user?->can($item['permission']));
            @endphp

            @if ($visible)
                <div class="{{ $group['title'] ? 'pt-4' : '' }} space-y-0.5">
                    @if ($group['title'])
                        <p class="px-3 pb-1 text-[11px] font-semibold tracking-wide text-primary-fixed-dim/60">
                            {{ __($group['title']) }}
                        </p>
                    @endif

                    @foreach ($visible as $item)
                        @php
                            // "imports.index" covers imports.* — but not a page that has
                            // an entry of its own (imports.gdb), or both would light up.
                            // (rtrim() was used here once; it strips characters, not a
                            // suffix, so "imports.index" became "imports".)
                            $routeBase = str_ends_with($item['route'], '.index') ? substr($item['route'], 0, -6) : $item['route'];
                            $ownEntry = $current !== null && $current !== $item['route'] && $allRoutes->contains($current);
                            $isActive = request()->routeIs($item['route'])
                                || (! $ownEntry && request()->routeIs($routeBase.'.*'));
                            $href = Route::has($item['route']) ? route($item['route']) : '#';
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
                            <span class="min-w-0">
                                <span class="block">{{ __($item['label']) }}</span>
                                @isset($item['hint'])
                                    <span class="block text-[11px] font-normal leading-snug {{ $isActive ? 'text-white/70' : 'text-primary-fixed-dim/70' }}">
                                        {{ __($item['hint']) }}
                                    </span>
                                @endisset
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach

        {{-- Planned services. Collapsed by default, and every entry is inert:
             these have no screen yet, so they are shown, not linked. --}}
        <div x-data="{ servicesOpen: false }" class="pt-4 mt-3 border-t border-white/10">
            <button type="button" @click="servicesOpen = ! servicesOpen"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                           text-primary-fixed-dim hover:bg-white/8 hover:text-white transition-all duration-150"
                    :aria-expanded="servicesOpen">
                <span class="material-symbols-outlined text-[22px] shrink-0"
                      style="font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;">home_repair_service</span>
                <span class="flex-1 text-start">{{ __('nav.services') }}</span>
                <span class="material-symbols-outlined text-[18px] shrink-0 transition-transform duration-200"
                      :class="servicesOpen && 'rotate-180'">expand_more</span>
            </button>

            {{-- Plain x-show: x-collapse measures the panel at init, and this one
                 starts hidden, so it would pin the height at zero. --}}
            <div x-show="servicesOpen" x-cloak class="ps-3 mt-0.5 space-y-0.5">
                @foreach ($serviceItems as $service)
                    @php
                        $serviceHref = $service['route'] ? route($service['route']) : null;
                        $serviceIsActive = $serviceHref && request()->routeIs($service['route']);
                    @endphp

                    @if ($serviceHref)
                        <a href="{{ $serviceHref }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all duration-150
                                  {{ $serviceIsActive
                                      ? 'bg-white/15 text-white'
                                      : 'text-primary-fixed-dim hover:bg-white/8 hover:text-white' }}"
                           {{ $serviceIsActive ? 'aria-current=page' : '' }}>
                            <span class="material-symbols-outlined text-[19px] shrink-0"
                                  style="font-variation-settings: 'FILL' {{ $serviceIsActive ? 1 : 0 }}, 'wght' 300, 'GRAD' 0, 'opsz' 24;">
                                {{ $service['icon'] }}
                            </span>
                            <span class="flex-1">{{ __($service['label']) }}</span>
                            @if ($service['soon'])
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full
                                             bg-tertiary-container/40 text-tertiary-container shrink-0">
                                    {{ __('nav.services_coming_soon_badge') }}
                                </span>
                            @endif
                        </a>
                    @else
                        <span aria-disabled="true"
                              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                                     text-primary-fixed-dim/60 cursor-default">
                            <span class="material-symbols-outlined text-[19px] shrink-0"
                                  style="font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;">
                                {{ $service['icon'] }}
                            </span>
                            <span class="flex-1">{{ __($service['label']) }}</span>
                            <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full
                                         bg-tertiary-container/40 text-tertiary-container shrink-0">
                                {{ __('nav.services_coming_soon_badge') }}
                            </span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    </nav>

    {{-- Divider --}}
    <div class="border-t border-white/10 mx-4"></div>

    {{-- Profile --}}
    <div class="px-4 py-4 shrink-0">
        <div class="flex items-center gap-3">
            {{-- The profile has no entry in the list above; the user card is its link. --}}
            <a href="{{ route('profile.index') }}" title="{{ __('nav.profile') }}"
               class="flex-1 min-w-0 flex items-center gap-3 rounded-xl -m-1 p-1 hover:bg-white/8 transition-colors
                      {{ request()->routeIs('profile.*') ? 'bg-white/15' : '' }}">
                <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center shrink-0 text-white text-sm font-bold">
                    {{ mb_substr(auth()->user()?->name ?? 'م', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()?->name }}</p>
                    <p class="text-primary-fixed-dim text-[11px] truncate">
                        {{ auth()->user()?->roles?->first()?->name ?? '' }}
                    </p>
                </div>
            </a>
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
