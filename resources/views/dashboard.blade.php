<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>الرئيسية — صكوكي</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-surface min-h-screen flex items-center justify-center">
    <div class="text-center">
        <h1 class="text-headline-lg font-arabic text-primary mb-md">
            مرحباً، {{ auth()->user()->name }}
        </h1>
        <p class="text-body-md text-on-surface-variant mb-lg">تسجيل الدخول ناجح ✓</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="bg-error text-on-error px-lg py-sm rounded-lg text-body-md">
                تسجيل الخروج
            </button>
        </form>
    </div>
</body>
</html>
