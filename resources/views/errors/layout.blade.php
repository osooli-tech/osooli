<!DOCTYPE html>
<html
    dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}"
    lang="{{ app()->getLocale() }}"
    x-data="{
        isDark: (function () {
            var t = localStorage.getItem('theme');
            return t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches);
        })(),
    }"
    :class="{ 'dark': isDark }"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — صكوكي</title>
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
</head>
<body class="bg-surface dark:bg-[#0b1c30] min-h-screen flex items-center justify-center font-sans antialiased">

    <div class="text-center px-6 max-w-md">

        {{-- Icon --}}
        <div class="flex justify-center mb-6">
            <div class="w-20 h-20 rounded-full flex items-center justify-center @yield('icon-bg')">
                <span class="material-symbols-outlined text-4xl @yield('icon-color')">@yield('icon')</span>
            </div>
        </div>

        {{-- Code --}}
        <p class="text-6xl font-bold text-primary dark:text-primary-fixed-dim mb-2">@yield('code')</p>

        {{-- Heading --}}
        <h1 class="text-2xl font-semibold text-on-surface dark:text-white mb-3">@yield('heading')</h1>

        {{-- Body --}}
        <p class="text-on-surface-variant dark:text-on-surface mb-8 leading-relaxed">@yield('body')</p>

        {{-- Back button --}}
        @auth
            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-xl font-medium hover:bg-primary-container transition-colors">
                <span class="material-symbols-outlined text-base">home</span>
                {{ __('errors.back_home') }}
            </a>
        @else
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-2 bg-primary text-on-primary px-6 py-3 rounded-xl font-medium hover:bg-primary-container transition-colors">
                <span class="material-symbols-outlined text-base">login</span>
                {{ __('auth.login_button') }}
            </a>
        @endauth

    </div>

</body>
</html>
