<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ __('landing.meta_title') }}</title>
    <meta name="description" content="{{ __('landing.meta_description') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ __('landing.meta_title') }}">
    <meta property="og:description" content="{{ __('landing.meta_description') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ app()->isLocale('ar') ? 'ar_SA' : 'en_US' }}">

    {{-- public/favicon.ico is Laravel's empty placeholder, so it answers 404 and
         the tab shows nothing. These are the Sakuki mark on a white plate. --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- The landing is a visitor's first request, so it loads its own bundle
         rather than the app's — no Tailwind, no Livewire, no icon font. --}}
    @vite(['resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body>

<a class="skip" href="#main">{{ __('landing.skip_to_content') }}</a>

@include('landing.sections.header')

<main id="main">
    @yield('content')
</main>

@include('landing.sections.footer')

</body>
</html>
