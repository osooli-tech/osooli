<?php

declare(strict_types=1);

/**
 * Vocabulary shared by every edit form.
 *
 * Kept in one file so a button, a confirmation or an error message reads the
 * same wherever it appears, instead of being re-worded per screen.
 */
return [
    // Controls
    'choose' => '— اختر —',
    'save' => 'حفظ',
    'cancel' => 'إلغاء',
    'create' => 'إضافة',
    'edit' => 'تعديل',
    'archive' => 'أرشفة',
    'restore' => 'استرجاع',
    'delete' => 'حذف',
    'search' => 'بحث',
    'optional' => 'اختياري',

    // Outcomes
    'created' => 'تمت الإضافة',
    'updated' => 'تم حفظ التعديل',
    'archived' => 'تمت الأرشفة',
    'restored' => 'تم الاسترجاع',
    'deleted' => 'تم الحذف',

    // Guards
    'confirm_archive' => 'أرشفة هذا السجل؟ يمكن استرجاعه لاحقاً.',
    'confirm_delete' => 'حذف نهائي لا يمكن التراجع عنه. متأكد؟',
    'no_results' => 'لا توجد نتائج',
    'search_choose' => 'اكتب للبحث ثم اختر…',
    'clear' => 'مسح',

    // Date picker
    'pick_date' => 'اختر التاريخ…',
    'today' => 'اليوم',
    'previous_month' => 'الشهر السابق',
    'next_month' => 'الشهر التالي',
    'gregorian_equivalent' => 'يوافق ميلادياً:',
    'hijri_equivalent' => 'يوافق هجرياً:',

    // Date added — filter and sortable column on every list
    'created_at' => 'تاريخ الإضافة',
    'created_from' => 'تاريخ الإضافة — من',
    'created_to' => 'تاريخ الإضافة — إلى',
    'sort_by_date' => 'الترتيب حسب تاريخ الإضافة',

    'unauthorized' => 'لا تملك صلاحية هذا الإجراء',

    /*
     * Shown when the optimistic lock trips: the record changed between opening
     * the form and saving it. The wording matters — the user must understand
     * that nothing was lost and what to do next.
     */
    'conflict' => 'عدّل مستخدم آخر هذا السجل بعد فتحك له. لم يُحفظ تعديلك حتى لا يُلغى عمله. أعد فتح السجل لترى وضعه الحالي ثم أعد إدخال تغييرك.',
];
