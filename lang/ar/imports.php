<?php

declare(strict_types=1);

return [
    'title' => 'استيراد البيانات',
    'kind' => [
        'label' => 'نوع الملف',
        'gdb' => 'قاعدة بيانات جغرافية (GDB مضغوط)',
        'documents' => 'مستندات PDF مضغوطة',
    ],
    'choose_file' => 'اختر ملفاً',
    'upload' => 'رفع',
    'uploading' => 'جارٍ الرفع…',
    'analyzing' => 'جارٍ الفحص…',
    'committing' => 'جارٍ الحفظ…',
    'preview' => [
        'title' => 'نتيجة الفحص',
        'total' => 'إجمالي العناصر',
        'will_create' => 'ستُضاف',
        // Documents import counts something different under the same shape:
        // "created" there is parcel–file LINKS (one deed can link to several
        // parcels), while "unmatched" counts FILES. The two are not a
        // matched pair, so they get their own, unit-explicit labels instead
        // of reusing will_create/unmatched — see import-wizard.blade.php.
        'will_create_links' => 'روابط ملفات بقطع ستُضاف',
        'will_update' => 'ستُحدَّث',
        'unmatched' => 'غير مطابقة',
        'unmatched_files' => 'ملفات غير مرتبطة بأي قطعة',
        'rule' => 'قاعدة المطابقة',
        'warnings' => 'تنبيهات',
    ],
    'confirm' => 'تأكيد الاستيراد',
    'cancel' => 'إلغاء',
    'completed' => 'اكتمل الاستيراد',
    'failed' => 'فشل الاستيراد',
    'start_over' => 'استيراد ملف آخر',
    'recent' => [
        'title' => 'آخر عمليات الاستيراد',
        'file' => 'الملف',
        'uploader' => 'بواسطة',
        'status' => 'الحالة',
        'date' => 'التاريخ',
        'empty' => 'لا توجد عمليات استيراد بعد.',
    ],
    'status' => [
        'uploading' => 'جارٍ الرفع',
        'uploaded' => 'تم الرفع',
        'analyzing' => 'قيد الفحص',
        'previewed' => 'بانتظار التأكيد',
        'committing' => 'قيد الحفظ',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
    ],
    'errors' => [
        'extension' => 'نوع الملف غير مدعوم. الأنواع المسموحة: :allowed',
        'invalid_chunk' => 'جزء الملف المرسل غير صالح.',
        'not_uploading' => 'انتهت مرحلة الرفع لهذه العملية.',
        'out_of_order' => 'وصل جزء من الملف بترتيب غير صحيح.',
        'size_exceeded' => 'تجاوز حجم الملف المرسل الحجم المصرح به لهذه العملية.',
        'size_mismatch' => 'حجم الملف المستلم (:actual) لا يطابق الحجم المتوقع (:expected).',
        'invalid_archive' => 'الملف تالف أو لا يطابق النوع المتوقع.',
        // Both purely client-side: the JS never receives a server response
        // for these, so unlike the keys above they are read through
        // @js(__(...)) in import-wizard.blade.php and handed to
        // uploadImport() rather than coming back in a JSON body.
        'stuck_resync' => 'يبدو أن عملية الرفع متوقفة. يرجى المحاولة مرة أخرى.',
        'unexpected_response' => 'استجاب الخادم بردٍّ غير متوقع. يرجى المحاولة مرة أخرى.',
    ],
    'warnings' => [
        // Arabic noun-number agreement needs more than :count substitution
        // (singular/dual/few/many/other all take a different noun form), so
        // this is a trans_choice() string with explicit count branches
        // rather than a plain __() placeholder — see
        // ParcelGeoJsonImporter::previewFeatures()/importFeatures().
        'no_geo_id' => '{0} جميع العناصر تحمل رقم تعريف الأرض (Geo_ID)، ولا يوجد ما يتم تجاهله.|{1} يتم تجاهل عنصر واحد بلا رقم تعريف الأرض (Geo_ID).|{2} يتم تجاهل عنصرين بلا رقم تعريف الأرض (Geo_ID).|[3,10] يتم تجاهل :count عناصر بلا رقم تعريف الأرض (Geo_ID).|[11,99] يتم تجاهل :count عنصرًا بلا رقم تعريف الأرض (Geo_ID).|[100,*] يتم تجاهل :count عنصر بلا رقم تعريف الأرض (Geo_ID).',
    ],
];
