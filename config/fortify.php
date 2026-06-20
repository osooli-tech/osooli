<?php

use Laravel\Fortify\Features;

return [
    'guard'    => 'web',
    'passwords'=> 'users',
    'username' => 'email',
    'email'    => 'email',

    'lowercase_usernames' => true,

    // بعد Login الناجح → OTP page
    'home' => '/dashboard',

    'prefix'   => '',
    'domain'   => null,
    'middleware'=> ['web'],

    'limiters' => [
        'login'      => 'login',       // 5 محاولات / دقيقة تلقائياً
        'two-factor' => 'two-factor',
    ],

    'views' => true,

    'features' => [
        // Login فقط — الباقي غير مطلوب في هذا المشروع
        // registration  ← لا (الأدمن يضيف المستخدمين يدوياً)
        // resetPasswords ← لا (OTP بريد يكفي)
        // twoFactorAuthentication ← لا (نبني OTP خاص بنا)
        // passkeys       ← لا
    ],
];
