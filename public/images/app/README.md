# Owner app screenshots

The tour section on the landing page (`resources/views/landing/sections/app.blade.php`)
reads these six files:

| file                     | screen                                        |
|--------------------------|-----------------------------------------------|
| `app-home.webp`          | الرئيسية — القيمة التقديرية ومؤشرات المحفظة   |
| `app-parcels.webp`       | العقارات — القائمة مع البحث والتصفية          |
| `app-deeds.webp`         | سجل الصكوك                                    |
| `app-parcel-detail.webp` | تفاصيل القطعة 256 — الحدود والأبعاد           |
| `app-parcel-owners.webp` | القطعة 262 — الملّاك والصكوك وطلب التعديل     |
| `app-map.webp`           | الخريطة — القطعة 264 وأدوات القياس            |

Portrait, 9:20. They render at about 250 CSS px wide, so the native 575–610 px
of a phone screenshot is already ample — do not upscale.

WebP, quality 85. The sources are phone screenshots that have been through
JPEG once already; PNG re-encodes those artefacts faithfully at roughly seven
times the weight (1.9 MB for the set, against 212 kB here).

Changing the order or adding a screen means editing the `$slides` array in the
section, plus the matching `tour_*` strings in `lang/{ar,en}/landing.php`.
