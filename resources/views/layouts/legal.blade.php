<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title') — {{ __('nav.app_name') }}</title>
    <meta name="robots" content="index, follow">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- These pages are reached from search and from inside the mobile app, so
         they carry the public bundle and the site's own header and footer —
         not the authenticated app's shell. --}}
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body>

<a class="skip" href="#main">{{ __('landing.skip_to_content') }}</a>

@include('landing.sections.header')

<main id="main" class="band band--surface legal">
    <div class="legal-inner">

        <header class="legal-head">
            <h1>@yield('title')</h1>
            @hasSection('updated')
                <p class="legal-updated">
                    {{ __('legal.last_updated') }}
                    <span class="num">@yield('updated')</span>
                </p>
            @endif
        </header>

        <article class="legal-body">
            @yield('content')
        </article>

        <nav class="legal-switch" aria-label="{{ __('landing.foot_company') }}">
            <a href="{{ route('privacy.policy') }}"
               @if (request()->routeIs('privacy.policy')) aria-current="page" @endif>
                {{ __('legal.privacy_link') }}
            </a>
            <a href="{{ route('terms.of.use') }}"
               @if (request()->routeIs('terms.of.use')) aria-current="page" @endif>
                {{ __('legal.terms_link') }}
            </a>
        </nav>

    </div>
</main>

@include('landing.sections.footer')

</body>
</html>
