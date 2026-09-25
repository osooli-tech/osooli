<?php

declare(strict_types=1);

/**
 * Vocabulary for the reference-data screen: plans, the location chain behind
 * them (country → region → city → district) and the engineering offices.
 *
 * Grouped by tab key rather than flattened, so a screen that already knows
 * which record type it is showing can look a label up directly.
 */
return [
    'title' => 'البيانات المرجعية',
    'subtitle' => 'إدارة المخططات والأحياء والمدن والمناطق والدول والمكاتب الهندسية',

    'tabs' => [
        'plans' => 'المخططات',
        'districts' => 'الأحياء',
        'cities' => 'المدن',
        'regions' => 'المناطق',
        'countries' => 'الدول',
        'offices' => 'المكاتب الهندسية',
    ],

    'create' => [
        'plans' => 'إضافة مخطط',
        'districts' => 'إضافة حي',
        'cities' => 'إضافة مدينة',
        'regions' => 'إضافة منطقة',
        'countries' => 'إضافة دولة',
        'offices' => 'إضافة مكتب هندسي',
    ],

    'edit' => [
        'plans' => 'تعديل مخطط',
        'districts' => 'تعديل حي',
        'cities' => 'تعديل مدينة',
        'regions' => 'تعديل منطقة',
        'countries' => 'تعديل دولة',
        'offices' => 'تعديل مكتب هندسي',
    ],

    'search' => [
        'plans' => 'ابحث برقم المخطط...',
        'districts' => 'ابحث باسم الحي...',
        'cities' => 'ابحث باسم المدينة...',
        'regions' => 'ابحث باسم المنطقة...',
        'countries' => 'ابحث باسم الدولة...',
        'offices' => 'ابحث باسم المكتب أو رقم الترخيص أو الهاتف...',
    ],

    'empty' => [
        'plans' => 'لا توجد مخططات مسجلة',
        'districts' => 'لا توجد أحياء مسجلة',
        'cities' => 'لا توجد مدن مسجلة',
        'regions' => 'لا توجد مناطق مسجلة',
        'countries' => 'لا توجد دول مسجلة',
        'offices' => 'لا توجد مكاتب هندسية مسجلة',
    ],

    'filters' => [
        'all_regions' => 'كل المناطق',
        'all_cities' => 'كل المدن',
        'all_districts' => 'كل الأحياء',
        'usage' => 'الارتباط',
        'usage_all' => 'الكل',
        'usage_used' => 'المرتبطة بـ :what فقط',
        'usage_unused' => 'غير المرتبطة بـ :what',
        'results' => 'عدد النتائج: :count',
        'clear' => 'مسح الفلاتر',
    ],

    'dependents_hint' => [
        'plans' => 'عدد القطع المسجّلة على هذا المخطط، ومنها المؤرشفة.',
        'districts' => 'عدد المخططات المرتبطة بالحي. يبقى صفرًا للأحياء المضافة من العنوان الوطني إلى أن يُربط بها مخطط.',
        'cities' => 'عدد الأحياء المسجّلة في المدينة. أغلب المدن والقرى ليس لها أحياء في بيانات العنوان الوطني، فيظهر صفر.',
        'regions' => 'عدد المدن المسجّلة في المنطقة.',
        'countries' => 'عدد المناطق المسجّلة في الدولة.',
        'offices' => 'عدد سجلات الحدود المنسوبة إلى المكتب.',
    ],

    /*
     * Column header for the number of records that depend on the row — the
     * same number that decides whether it can be deleted.
     */
    'dependents' => [
        'plans' => 'القطع',
        'districts' => 'المخططات',
        'cities' => 'الأحياء',
        'regions' => 'المدن',
        'countries' => 'المناطق',
        'offices' => 'سجلات الحدود',
    ],

    /*
     * Shown when a delete is refused because other records still point at the
     * row. Each message names the count and the next step, because "لا يمكن
     * الحذف" on its own leaves the user with nowhere to go.
     */
    'blocked' => [
        'plans' => 'لا يمكن حذف هذا المخطط لارتباطه بـ :count قطعة، بما فيها القطع المؤرشفة. انقل هذه القطع إلى مخطط آخر ثم أعد المحاولة.',
        'districts' => 'لا يمكن حذف هذا الحي لارتباطه بـ :count مخطط. انقل هذه المخططات إلى حي آخر ثم أعد المحاولة.',
        'cities' => 'لا يمكن حذف هذه المدينة لارتباطها بـ :count حي. انقل هذه الأحياء إلى مدينة أخرى ثم أعد المحاولة.',
        'regions' => 'لا يمكن حذف هذه المنطقة لارتباطها بـ :count مدينة. انقل هذه المدن إلى منطقة أخرى ثم أعد المحاولة.',
        'countries' => 'لا يمكن حذف هذه الدولة لارتباطها بـ :count منطقة. انقل هذه المناطق إلى دولة أخرى ثم أعد المحاولة.',
        'offices' => 'لا يمكن حذف هذا المكتب الهندسي لارتباطه بـ :count سجل حدود. أزل نسبة هذه السجلات إليه ثم أعد المحاولة.',
    ],

    // Fields
    'plan_no' => 'رقم المخطط',
    'district' => 'الحي',
    'city' => 'المدينة',
    'region' => 'المنطقة',
    'country' => 'الدولة',
    'name_ar' => 'الاسم بالعربية',
    'name_en' => 'الاسم بالإنجليزية',
    'iso_code' => 'رمز الدولة (ISO)',
    'office_name' => 'اسم المكتب',
    'license_no' => 'رقم الترخيص',
    'phone' => 'الهاتف',
    'email' => 'البريد الإلكتروني',

    // Values
    'unassigned' => 'غير محدد',
    'district_hint' => 'يمكن ترك الحي فارغاً وتحديده لاحقاً.',

    // Delete dialog
    'delete_title' => 'حذف سجل مرجعي',
    'delete_blocked_title' => 'تعذّر الحذف',
    'close' => 'إغلاق',
];
