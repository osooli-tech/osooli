<?php

declare(strict_types=1);

return [
    // Page
    'title' => 'لوحة التحكم',
    'welcome' => 'مرحباً، :name',

    // Section headings
    'section_kpi' => 'المؤشرات الرئيسية',
    'section_charts' => 'التوزيعات والرسوم البيانية',
    'section_operational' => 'البيانات التشغيلية',

    // ── Section 1: KPI cards ────────────────────────────────────
    'total_parcels' => 'إجمالي القطع',
    'total_deeds' => 'إجمالي الصكوك',
    'total_area' => 'إجمالي المساحة',
    'avg_area' => 'متوسط مساحة القطعة',
    'max_min_area' => 'أكبر / أصغر قطعة',
    'min_area_label' => 'أصغر',
    'total_plans' => 'عدد المخططات',
    'total_owners' => 'عدد الملاك',
    'multi_owner_deeds' => 'صكوك متعددة الملاك',
    'pending_requests' => 'طلبات التعديل المعلّقة',
    'top_owner' => 'أكثر مالك حيازةً',
    'deeds' => 'صك',
    'area_unit_sqm' => 'م²',
    'area_unit_million' => 'م',
    'needs_action' => 'تنتظر إجراء',
    'all_clear' => 'لا طلبات معلّقة',

    // ── Section 2: charts ───────────────────────────────────────
    'distribution_by_deed_status' => 'حالة الصكوك',
    'distribution_by_type' => 'نوع العقار',
    'distribution_by_land_transaction' => 'نوع التعامل',
    'distribution_by_city' => 'التوزيع حسب المدينة',
    'distribution_by_district' => 'التوزيع حسب الحي',
    'distribution_linked_decision' => 'الارتباط بقرار مساحي',
    'distribution_by_office' => 'المكاتب الهندسية',
    'linked' => 'مرتبطة',
    'not_linked' => 'غير مرتبطة',
    'no_data' => 'لا توجد بيانات',

    // ── Section 3: operational ──────────────────────────────────
    'pending_mod_requests' => 'طلبات التعديل المعلّقة',
    'all_clear' => 'لا طلبات معلّقة',
    'last_sync' => 'آخر مزامنة',
    'never' => 'لم تتم بعد',
    'sync_status_success' => 'ناجحة',
    'sync_status_failed' => 'فاشلة',
    'sync_status_partial' => 'جزئية',
    'records_imported' => 'سجل مستورَد',
    'active_users' => 'المستخدمون النشطون',
    'active_label' => 'مستخدم نشط حالياً',

    // ── Map & recent ────────────────────────────────────────────
    'mapbox_missing' => 'أضف MAPBOX_TOKEN في .env لتفعيل الخريطة',
    'parcel_details' => 'تفاصيل القطعة',
    'click_parcel' => 'انقر على قطعة في الخريطة لعرض تفاصيلها',
    'recent_parcels' => 'آخر القطع المضافة',
    'recent_alerts' => 'تنبيهات الصكوك القديمة',
    'no_alerts' => 'لا توجد تنبيهات',
    'view_all' => 'عرض الكل',
];
