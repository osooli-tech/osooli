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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('portal.dashboard_title')) — {{ __('nav.app_name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">

    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface dark:bg-[#0d1420] text-on-surface dark:text-white min-h-screen">

    {{-- Simplified topbar — no sidebar. The portal is one owner's own read-only
         view, not the internal team's multi-section dashboard. --}}
    <header class="sticky top-0 z-30 bg-primary text-white shadow-sm">
        <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2 shrink-0">
                <img src="{{ asset('images/logo-icon-sm.png') }}" alt="{{ __('nav.app_name') }}" class="h-8 w-8">
                <span class="font-semibold hidden sm:inline">{{ __('nav.app_name') }}</span>
            </a>

            <nav class="flex items-center gap-1 text-sm font-medium">
                <a href="{{ route('portal.dashboard') }}"
                   class="px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('portal.dashboard') ? 'bg-white/15' : 'hover:bg-white/10' }}">
                    {{ __('portal.nav_dashboard') }}
                </a>
                <a href="{{ route('portal.parcels.index') }}"
                   class="px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('portal.parcels.*') ? 'bg-white/15' : 'hover:bg-white/10' }}">
                    {{ __('portal.nav_parcels') }}
                </a>
            </nav>

            <div class="flex items-center gap-3 shrink-0">
                <button @click="toggleTheme()" class="text-white/80 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[20px]" x-text="isDark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>
                <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
                   class="text-sm font-medium text-white/80 hover:text-white transition-colors">
                    {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
                </a>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-white/80 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span class="hidden sm:inline">{{ __('portal.logout') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-5 py-6">
        @yield('content')
    </main>

    @stack('scripts')

</body>
</html>
