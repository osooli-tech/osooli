<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — {{ __('nav.app_name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-surface min-h-screen">
    <main class="max-w-3xl mx-auto px-6 py-12">

        {{-- These pages are reached directly from the app and from search, so
             the language switch has to live here — there is no shell around
             them carrying one. --}}
        <div class="flex justify-end mb-2">
            <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
               class="flex items-center gap-1.5 text-sm font-medium text-on-surface-variant
                      hover:text-secondary transition-colors">
                <span class="material-symbols-outlined text-[18px]">language</span>
                {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
            </a>
        </div>

        <header class="mb-10 text-center">
            <a href="{{ route('login') }}" class="inline-block mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="{{ __('nav.app_name') }}" class="h-16 mx-auto"
                     onerror="this.style.display='none'">
            </a>
            <h1 class="text-3xl font-bold text-on-surface">@yield('title')</h1>
            <p class="mt-2 text-sm text-on-surface-variant">
                {{ __('legal.last_updated') }}: @yield('updated')
            </p>
        </header>

        <article class="space-y-8 leading-relaxed text-on-surface">
            @yield('content')
        </article>

        <footer class="mt-14 pt-6 border-t border-outline-variant text-center text-sm text-on-surface-variant">
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('privacy.policy') }}" class="hover:underline">{{ __('legal.privacy_link') }}</a>
                <span>·</span>
                <a href="{{ route('terms.of.use') }}" class="hover:underline">{{ __('legal.terms_link') }}</a>
            </div>
            <p class="mt-3">© {{ date('Y') }} {{ __('nav.app_name') }} — {{ __('legal.rights_reserved') }}</p>
        </footer>
    </main>
</body>
</html>
