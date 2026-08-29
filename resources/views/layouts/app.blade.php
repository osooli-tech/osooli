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
        sidebarOpen: window.matchMedia('(min-width: 1024px)').matches
            ? localStorage.getItem('sidebarOpen') !== 'false'
            : false,
        toggleSidebar () {
            this.sidebarOpen = ! this.sidebarOpen;
            if (window.matchMedia('(min-width: 1024px)').matches) {
                localStorage.setItem('sidebarOpen', this.sidebarOpen);
            }
        },
    }"
    :class="{ 'dark': isDark }"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('nav.dashboard')) — {{ __('nav.app_name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

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

    {{-- Mobile backdrop — only visible below lg while the drawer is open --}}
    <div x-show="sidebarOpen"
         @click="toggleSidebar()"
         class="fixed inset-0 bg-black/50 z-40 lg:hidden"
         x-cloak></div>

    {{-- Topbar: fixed. On desktop it offsets by the sidebar width when open;
         on mobile it always spans full width (the sidebar overlays it). --}}
    <header class="fixed top-0 end-0 start-0 h-16 z-30
                   bg-surface-container-lowest dark:bg-[#161b22]
                   border-b border-outline-variant dark:border-white/10
                   flex items-center gap-4 px-4 sm:px-6
                   transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'lg:start-[280px]' : 'lg:start-0'">

        {{-- Sidebar toggle --}}
        <button @click="toggleSidebar()"
                class="w-9 h-9 flex items-center justify-center rounded-xl shrink-0
                       text-on-surface-variant dark:text-on-primary-container
                       hover:bg-surface-container dark:hover:bg-white/10 transition-colors"
                title="{{ __('nav.toggle_sidebar') }}">
            <span class="material-symbols-outlined text-[22px]">menu</span>
        </button>

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

            <livewire:notifications.notification-bell />

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

    {{-- Content area — full width on mobile, offset by the sidebar on desktop --}}
    <main class="mt-16 min-h-[calc(100vh-4rem)] p-4 sm:p-6 transition-all duration-300 ease-in-out ms-0"
          :class="sidebarOpen ? 'lg:ms-[280px]' : 'lg:ms-0'">
        @yield('content')
    </main>

    @livewireScripts
    @stack('scripts')

</body>
</html>
