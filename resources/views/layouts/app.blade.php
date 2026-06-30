<!DOCTYPE html>
<html
    dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}"
    lang="{{ app()->getLocale() }}"
    x-data="{
        isDark: (function () {
            var t = localStorage.getItem('theme');
            return t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches);
        })(),
        toggleTheme () {
            this.isDark = ! this.isDark;
            localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
        },
    }"
    :class="{ 'dark': isDark }"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('nav.dashboard')) — صكوكي</title>

    {{-- Anti-FOUC: apply dark class before first paint --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface dark:bg-[#0d1117] text-on-surface dark:text-white min-h-screen overflow-x-hidden">

    <x-sidebar />

    {{-- Topbar: fixed, spans the content area (start-[280px] = right:280px in RTL) --}}
    <header class="fixed top-0 start-[280px] end-0 h-16 z-30
                   bg-surface-container-lowest dark:bg-[#161b22]
                   border-b border-outline-variant dark:border-white/10
                   flex items-center gap-4 px-6">

        {{-- Page title + breadcrumb (fills available space) --}}
        <div class="flex-1 min-w-0">
            @hasSection('breadcrumb')
                <nav class="flex items-center gap-1 text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                    <a href="{{ route('dashboard') }}"
                       class="hover:text-on-surface dark:hover:text-white transition-colors">
                        {{ __('nav.dashboard') }}
                    </a>
                    @yield('breadcrumb')
                </nav>
            @endif
            <h1 class="text-base font-semibold text-on-surface dark:text-white leading-none truncate">
                @yield('page-title', __('nav.dashboard'))
            </h1>
        </div>

        {{-- Optional search slot --}}
        @hasSection('topbar-search')
            <div class="shrink-0 w-56">@yield('topbar-search')</div>
        @endif

        {{-- Controls --}}
        <div class="flex items-center gap-1 shrink-0">

            <button @click="toggleTheme()"
                    class="w-9 h-9 flex items-center justify-center rounded-xl
                           text-on-surface-variant dark:text-on-primary-container
                           hover:bg-surface-container dark:hover:bg-white/10 transition-colors"
                    :title="isDark ? '{{ __('nav.light_mode') }}' : '{{ __('nav.dark_mode') }}'">
                <span class="material-symbols-outlined text-[20px]"
                      x-text="isDark ? 'light_mode' : 'dark_mode'">dark_mode</span>
            </button>

            <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-xs font-semibold
                      text-on-surface-variant dark:text-on-primary-container
                      hover:bg-surface-container dark:hover:bg-white/10 transition-colors"
               title="{{ app()->isLocale('ar') ? 'English' : 'العربية' }}">
                {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
            </a>

            <button class="relative w-9 h-9 flex items-center justify-center rounded-xl
                           text-on-surface-variant dark:text-on-primary-container
                           hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
            </button>

        </div>

        @can('parcels.view_map')
            <a href="#"
               class="shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-xl
                      bg-secondary hover:brightness-110 text-white text-sm font-medium transition-all">
                <span class="material-symbols-outlined text-[18px]">public</span>
                {{ __('nav.map_browser') }}
            </a>
        @endcan

    </header>

    {{-- Toast notifications --}}
    <x-toast />

    {{-- Content area --}}
    <main class="ms-[280px] mt-16 min-h-[calc(100vh-4rem)] p-6">
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')

</body>
</html>
