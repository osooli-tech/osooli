# Owner app screenshots

The tour section on the landing page (`resources/views/landing/sections/app.blade.php`)
reads these eight files:

| file                     | screen                                              |
|--------------------------|-----------------------------------------------------|
| `app-map.webp`           | الخريطة — الهيدر الترحيبي وبطاقة القطعة المختارة    |
| `app-stats.webp`         | الإحصائيات — القيمة التقديرية ومؤشرات المحفظة       |
| `app-charts.webp`        | الإحصائيات — الوصول السريع وتوزيع القطع             |
| `app-parcel-detail.webp` | تفاصيل القطعة 339 — الشكل والأبعاد وبيانات القطعة   |
| `app-parcel-survey.webp` | القطعة 339 — الحدود والقرار المساحي والمستندات      |
| `app-search.webp`        | البحث — بالصك أو القطعة أو المخطط أو اسم المالك     |
| `app-assistant.webp`     | المساعد الذكي — شاشة قريباً                          |
| `app-account.webp`       | الحساب — ملف المالك وإعدادات التطبيق                |

Portrait, 9:20 (575 × 1280 here). They render at about 250 CSS px wide, so a
native phone screenshot is already ample — do not upscale.

WebP, quality 85. The sources are phone screenshots that have been through
JPEG once already; PNG re-encodes those artefacts faithfully at roughly seven
times the weight (216 kB for this set).

Changing the order or adding a screen means editing the `$slides` array in the
section, plus the matching `tour_*` strings in `lang/{ar,en}/landing.php`.
