<!DOCTYPE html>
<html x-data="themeManager()" :class="{ 'dark': isDark }" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('auth.login_title')) — صكوكي</title>
    {{-- Anti-FOUC: apply dark class before first paint --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/auth.js'])
</head>
<body class="overflow-hidden">

    <div class="flex flex-row h-screen w-full">

        {{-- Form Side (always on left column) --}}
        <main class="w-full md:w-1/2 min-h-screen flex flex-col bg-surface dark:bg-[#000e20] relative overflow-hidden">

            {{-- Decorative blobs --}}
            <div class="absolute top-0 end-0 w-64 h-64 bg-secondary opacity-5 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 start-0 w-96 h-96 bg-tertiary-container opacity-5 rounded-full translate-y-1/2 -translate-x-1/2 blur-3xl pointer-events-none"></div>

            {{-- Top controls: language switcher + theme toggle --}}
            <div class="flex items-center justify-between px-8 md:px-16 pt-6 z-20">
                <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
                   class="flex items-center gap-1.5 text-sm font-medium text-on-surface-variant dark:text-on-primary-container hover:text-secondary dark:hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[18px]">language</span>
                    {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
                </a>
                <button @click="toggle()"
                        class="flex items-center gap-1.5 text-sm font-medium text-on-surface-variant dark:text-on-primary-container hover:text-secondary dark:hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[20px]"
                          x-text="isDark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>
            </div>

            {{-- Form content --}}
            <div class="flex-grow flex items-center justify-center px-8 md:px-16 z-10">
                <div class="w-full max-w-md">
                    @yield('form-section')
                </div>
            </div>

            {{-- Bottom spacer --}}
            <div class="py-4"></div>

        </main>

        {{-- Brand Side (desktop only, always dark navy) --}}
        <aside class="hidden md:flex md:w-1/2 min-h-screen brand-panel relative overflow-hidden flex-col items-center justify-center">
            {{-- Background overlay slot (city map SVG, gradients) --}}
            @yield('brand-overlay')
            <div class="relative z-10 flex flex-col items-center text-center px-16 space-y-6">
                @yield('brand-panel')
            </div>
            <div class="absolute -bottom-20 -end-20 w-96 h-96 bg-secondary opacity-10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -top-20 -start-20 w-96 h-96 bg-tertiary-container opacity-5 rounded-full blur-3xl pointer-events-none"></div>
        </aside>

    </div>

    @stack('scripts')

</body>
</html>
