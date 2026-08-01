<?php

declare(strict_types=1);

return [
    // Auth
    'otp_sent' => 'تم إرسال رمز التحقق',
    'otp_invalid' => 'رمز التحقق غير صحيح',
    'phone_not_registered' => 'رقم الجوال غير مسجّل',
    'too_many_attempts' => 'حاول بعد قليل',
    'logged_out' => 'تم تسجيل الخروج',
    'unauthenticated' => 'غير مصرّح لك بالوصول',
    'not_found' => 'العنصر غير موجود',
    'server_error' => 'حدث خطأ غير متوقع، حاول لاحقًا',

    // Modification requests
    'modification_request_created' => 'تم إرسال طلب التعديل',
    'field_not_editable' => 'هذا الحقل غير قابل للتعديل',
    'parcel_not_owned' => 'القطعة غير موجودة',

    // Profile
    'profile_updated' => 'تم تحديث البيانات',

    'modification_statuses' => [
        'pending' => 'قيد المراجعة',
        'sent_to_arcgis' => 'أُرسل للتحديث',
        'applied' => 'تم التطبيق',
        'rejected' => 'مرفوض',
    ],
];
