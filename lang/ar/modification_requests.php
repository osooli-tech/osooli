<?php

declare(strict_types=1);

return [
    'title' => 'طلبات التعديل',
    'subtitle' => 'طلبات تعديل بيانات القطع المقدَّمة من الملّاك',
    'total' => 'طلب|طلبات',
    'empty' => 'لا توجد طلبات تعديل',
    'empty_filtered' => 'لا توجد طلبات بهذه الحالة',
    'search_placeholder' => 'ابحث برقم القطعة أو اسم الحقل...',

    // Table columns
    'col_parcel' => 'رقم القطعة',
    'col_owner' => 'المالك',
    'col_field' => 'الحقل',
    'col_old' => 'القيمة الحالية',
    'col_new' => 'القيمة المطلوبة',
    'col_status' => 'الحالة',
    'col_date' => 'تاريخ الطلب',
    'col_actions' => 'إجراءات',

    // Status labels
    'status' => [
        'all' => 'الكل',
        'pending' => 'انتظار',
        'sent_to_arcgis' => 'أُرسل لـ ArcGIS',
        'applied' => 'مُطبَّق',
        'rejected' => 'مرفوض',
    ],

    // Detail modal
    'modal_title' => 'تفاصيل الطلب',
    'request_info' => 'معلومات الطلب',
    'current_value' => 'القيمة الحالية',
    'requested_value' => 'القيمة المطلوبة',
    'submitted_at' => 'تاريخ التقديم',
    'resolved_at' => 'تاريخ الحل',
    'notes_label' => 'الملاحظات الحالية',
    'manager_note' => 'ملاحظة (اختيارية)',
    'manager_note_placeholder' => 'سبب التغيير أو ملاحظة للفريق...',
    'close' => 'إغلاق',

    // Actions
    'action_send' => 'إرسال لـ ArcGIS',
    'action_apply' => 'تأكيد التطبيق',
    'action_reject' => 'رفض',

    // Toasts
    'status_updated' => 'تم تحديث حالة الطلب',
    'invalid_transition' => 'تغيير الحالة هذا غير مسموح',

    // Audit
    'audit_changed' => 'تغيير حالة طلب التعديل',
];
