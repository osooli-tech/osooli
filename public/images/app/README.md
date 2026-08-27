# Owner app screenshots

The tour section on the landing page (`resources/views/landing/sections/app.blade.php`)
reads these six files. Drop the real device screenshots in with exactly these names:

| file                    | screen                                        |
|-------------------------|-----------------------------------------------|
| `app-home.png`          | الرئيسية — القيمة التقديرية ومؤشرات المحفظة   |
| `app-parcels.png`       | العقارات — القائمة مع البحث والتصفية          |
| `app-deeds.png`         | سجل الصكوك                                    |
| `app-parcel-detail.png` | تفاصيل القطعة — الحدود والأبعاد               |
| `app-parcel-owners.png` | القطعة — الملّاك والصكوك وطلب التعديل         |
| `app-map.png`           | الخريطة — القطع وملخّص القطعة المختارة        |

Portrait, ideally 1080×2340 or the same 9:19.5 ratio. They are rendered at
about 250 CSS px wide, so anything above ~750 px wide is wasted bytes —
compress before committing.

Changing the order or adding a screen is a matter of editing the `$slides`
array in the section, plus the matching `tour_*` strings in `lang/{ar,en}/landing.php`.
