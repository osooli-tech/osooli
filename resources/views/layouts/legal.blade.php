<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — صكوكي</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-surface min-h-screen">
    <main class="max-w-3xl mx-auto px-6 py-12">
        <header class="mb-10 text-center">
            <a href="{{ route('login') }}" class="inline-block mb-6">
                <img src="{{ asset('images/logo.png') }}" alt="صكوكي" class="h-16 mx-auto"
                     onerror="this.style.display='none'">
            </a>
            <h1 class="text-3xl font-bold text-on-surface">@yield('title')</h1>
            <p class="mt-2 text-sm text-on-surface-variant">آخر تحديث: @yield('updated')</p>
        </header>

        <article class="space-y-8 leading-relaxed text-on-surface">
            @yield('content')
        </article>

        <footer class="mt-14 pt-6 border-t border-outline-variant text-center text-sm text-on-surface-variant">
            <div class="space-x-4 space-x-reverse">
                <a href="{{ route('privacy.policy') }}" class="hover:underline">سياسة الخصوصية</a>
                <span>·</span>
                <a href="{{ route('terms.of.use') }}" class="hover:underline">شروط الاستخدام</a>
            </div>
            <p class="mt-3">© {{ date('Y') }} صكوكي — جميع الحقوق محفوظة</p>
        </footer>
    </main>
</body>
</html>
