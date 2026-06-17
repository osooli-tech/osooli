# Osooli (أصولي) — ملخص شامل للمشروع + قاعدة البيانات
## (Handoff كامل لـ Claude Code - يبدأ من هنا)

> هذا الملف يجمع كل شيء: نظرة عامة على المشروع، الـ stack، حالة البيئة،
> وتصميم قاعدة البيانات النهائي الكامل. اقرأه بالكامل قبل البدء بأي كود.

---

# الجزء 1: نظرة عامة على المشروع

## 1.1 المشروع
- **الاسم**: أصولي / OSOOLI — منصة مساحة وإدارة عقارية ذكية
- **العميل**: شركة الإسناد الدولية (Mohammad Tariq Zuhiery)
- **الفريق**: Mostafa Ahmed (Backend) + Abdalwahab Salah (GIS/Frontend)
- **السوق**: السعودية، عربي RTL بالكامل
- **مدة المرحلة 1**: 6-7 أسابيع، 4 milestones (25% لكل واحد)، دعم 3 أشهر بعد الإطلاق
- **تكاليف السيرفر/الاستضافة**: على العميل (غير مشمولة في سعر المشروع)

## 1.2 نطاق المرحلة 1 (نهائي ومتفق عليه)

6 ميزات أساسية:
1. **المتصفح الجغرافي (Map Viewer)** — Mapbox GL JS (أساسي) + OpenStreetMap/Leaflet
   (احتياطي)، القطع من PostGIS عبر GeoServer WFS، تبديل صور الأقمار الصناعية،
   ضغط على القطعة → لوحة تفاصيل
2. **Dashboard (KPIs وملخص)** — إجمالي القطع، حالة الصكوك، عدد القرارات المساحية،
   إجمالي المساحة، التوزيع حسب المدينة/الحي
3. **قائمة الصكوك (Sukuk Register)** — جدول قابل للبحث/الترتيب، pagination من السيرفر،
   معاينة/تنزيل الصك والقرار المساحي
4. **تنزيل المستندات** — روابط pre-signed (صلاحية 15 دقيقة) من object storage،
   كل التنزيلات تُسجل في audit trail
5. **تصدير القوائم PDF/Excel/CSV** — يحترم الفلاتر النشطة، عبر Laravel Jobs queue
6. **البحث** — برقم الصك، رقم القطعة، رقم المخطط، اسم المالك، المدينة/الحي
   (PostGIS spatial query)، فلتر الحالة

**أساسيات مشمولة في المرحلة 1**:
- Authentication (تسجيل دخول + OTP عبر Unifonic + صلاحيات Admin/Viewer)
- Audit Log (تتبع التنزيلات وسجل الوصول، ثابت/immutable)
- صفحة تفاصيل القطعة (من الخريطة أو القائمة)
- Data Migration (استيراد قاعدة ArcGIS الجغرافية للعميل إلى PostGIS)

**خارج نطاق المرحلة 1**: تطبيق الموبايل (Flutter - عرض توضيحي فقط)، وحدة مهندس
المساحة، AI Chatbot، تكامل حكومي، نظام دفع.

## 1.3 الـ Stack النهائي (محسوم - لا مزيد من المقارنات)

| الطبقة | التقنية |
|---|---|
| Backend + Dashboard | **Laravel 11** + Blade + **Livewire 3** + Alpine.js (لا Next.js، لا Filament) |
| Mobile (Phase 2) | Flutter |
| Database | **PostgreSQL 16 + PostGIS 3.5** |
| GIS Server | **GeoServer 2.x** (WMS/WFS/WMTS) |
| Frontend Map | **Mapbox GL JS** + OpenStreetMap/Leaflet (احتياطي) |
| Cloud | Oracle Cloud KSA أو AWS KSA (تكلفة العميل) |
| File Storage | OCI Object Storage أو AWS S3 |
| SMS/OTP | **Unifonic** |
| Queue/Cache | Redis (أو Laravel Jobs DB driver للتصدير) |
| API Auth (Flutter) | Laravel Sanctum |
| RBAC | Spatie Laravel Permission |
| CI/CD | GitHub Actions |
| OCR | **محذوف تماماً من النطاق** |

## 1.4 قيود يجب تذكرها دائماً
- **لا Docker** (قرار العميل) — تثبيت مباشر PHP/PostgreSQL/Redis على Windows
- **لا Next.js/React/Filament** للوحة التحكم — Blade + Livewire + Alpine فقط
- **لا OCR** في أي مكان بالنظام
- كل المحتوى عربي RTL
- SRID = **4326** (WGS84 lat/lng) — تم تأكيده من ملف GeoJSON الفعلي
  (وليس 32638 كما كان مفترضاً سابقاً)

---

# الجزء 2: حالة البيئة الحالية (Windows)

- ✅ PostgreSQL 16 + pgAdmin 4 مثبتين
- ✅ PostGIS 3.5 Bundle مثبت
- ⏳ **التالي**: إكمال خطوات Laravel 11 (الخطوات 1-4 من أصل 8 منفذة):
  1. فحص المتطلبات (php 8.2+, composer, node 18+, npm, git, psql) ✅
  2. `composer create-project laravel/laravel osooli-platform "11.*"` ✅
  3. تثبيت: livewire/livewire, laravel/sanctum, spatie/laravel-permission,
     predis/predis, alpinejs ✅
  4. `.env` لـ `DB_CONNECTION=pgsql`, `DB_DATABASE=osooli_db`,
     `CREATE EXTENSION postgis;`, `php artisan migrate` ✅
  5-8. **متبقي**: هيكل المجلدات، migrations الأساسية (هذا الملف يوفرها الآن)،
     إعداد Sanctum، Tailwind/Vite، فحص نهائي

---

# الجزء 3: تصميم قاعدة البيانات — النهائي (17 جدول)

> الملف الكامل: **`osooli_schema.sql`** (PostgreSQL + PostGIS، جاهز للتشغيل
> مباشرة). هذا الجزء يشرح القرارات والمنطق وراءه.

## 3.1 الهيكل الجغرافي (owned by us)
```
countries → regions → cities → districts → plans
```
- `cities` = كل بلدة/مدينة بحجمها (كبيرة أو صغيرة، مثل الخرج/الدرعية) كصفوف
  مستقلة تحت `region` واحدة — **لا يوجد جدول "محافظات" منفصل**
- `plans.district_id` → يحدد المدينة/المنطقة/الدولة عبر joins

## 3.2 الجداول الأساسية

| الجدول | المصدر | الوصف |
|---|---|---|
| `owners` | عندنا | الملاك - بيانات تواصل (`national_id` فريد لو موجود) |
| `engineering_offices` | عندنا | المكاتب الهندسية (الرفع المساحي) |
| `parcels` | ArcGIS (sync) | القطعة الجغرافية - `geom MultiPolygon EPSG:4326`، `geo_id` فريد = مفتاح المزامنة |
| `deeds` | ArcGIS (sync) | الصكوك - 1:N من parcels (تاريخ صكوك)، `deed_area` = مساحة الصك |
| `deed_owners` | ArcGIS (sync) | **جدول وسيط** - ملكية مشتركة بين الصك والملاك |
| `parcel_boundaries` | ArcGIS (sync) | الحدود/الأبعاد - 1:1، `measured_area` = المساحة حسب الطبيعة |
| `survey_decisions` | ArcGIS (sync) | القرارات المساحية - 1:N |
| `parcel_photos` | ArcGIS (sync) | صور القطعة - 1:N، `photo_url` رابط Drive |
| `modification_requests` | عندنا | طلبات تعديل من الملاك → تُرسل لـ Al-Esnad يدوياً |
| `users` | عندنا | مستخدمو لوحة التحكم (admin/viewer) — **مختلفين عن owners** |
| `audit_logs` | عندنا | سجل تدقيق ثابت (دخول/تنزيل/تصدير) |
| `sync_log` | عندنا | سجل عمليات المزامنة من ArcGIS |

## 3.3 أهم القرارات الهيكلية (لماذا التصميم هكذا)

### القطعة والوحدات الفرعية (`parent_parcel_id`)
`parcels` جدول **ذاتي العلاقة**:
- صف بدون `parent_parcel_id` = أرض/عمارة أصلية (من ArcGIS، له `geom`)
- صف بـ `parent_parcel_id` معبأ = شقة داخل تلك العمارة (`geom = NULL`،
  تُدخل يدوياً من فريق أصولي - **ليس من ArcGIS**)

### الملكية المشتركة (`deed_owners`)
**تأكد من العميل (Al-Esnad)**: ArcGIS يمثل كل مالك كسجل (Feature) مستقل بنفس
`Geo_ID` + `Deed_No` (نفس geometry). لذلك سكربت الاستيراد **لازم يجمّع**
(`GROUP BY Geo_ID, Deed_No`):
- مجموعة بسجل واحد → مالك واحد → صف `deed_owners` واحد
- مجموعة بأكثر من سجل (نفس Geo_ID/Deed_No، Woner_ID مختلف) → صف واحد في
  `parcels`/`deeds` + عدة صفوف `deed_owners`

⚠️ **تكرار Geo_ID ليس خطأ بالضرورة** — فقط لو اختلفت الـ geometry أو
Deed_No بين السجلات المتكررة فهذا يحتاج مراجعة يدوية.

`ownership_share` (نسبة كل مالك) غالباً **NULL** — النسب مكتوبة كنص داخل
وثيقة الصك نفسها، وOCR خارج النطاق، فلا تُستخرج تلقائياً.

`source_gdb_id` موجود في `parcels` (OBJECTID تمثيلي) **و** `deed_owners`
(OBJECTID لكل سجل مالك على حدة) — للتتبع الدقيق عند إعادة المزامنة.

### المساحات الثلاثة (لا تخلط بينها)
1. `deeds.deed_area` = مساحة الصك القانونية (من حقل `Area`/`Survey_Area` في ArcGIS)
2. `parcel_boundaries.measured_area` = "المساحة حسب الطبيعة" (محسوبة من
   الأبعاد N/S/E/W أو رفع مساحي حقيقي)
3. `ST_Area(parcels.geom)` = محسوبة لحظياً من PostGIS — **غير مخزنة**، تُحسب
   عند الحاجة فقط

### التاريخ الهجري (`deed_date_hijri`)
`varchar(10)` مثل `'1435-03-08'` — **ليس** تاريخ Gregorian حقيقي. القيمة
الأصلية في ArcGIS مخزنة كـ epoch مللي ثانية سالب يمثّل أرقام Y-M-D هجرية
وكأنها Gregorian. عند الاستيراد: استخرج الأرقام فقط (Y-M-D) **بدون أي تحويل
تقويمي حقيقي** وخزّنها كنص.

### تاريخ الرفع المساحي (`survey_date`)
حقل nullable في `parcel_boundaries` — العميل (Al-Esnad) سيضيفه في ArcGIS
لاحقاً وسيأتي عبر sync. لا يُملأ حالياً. الصيغة النهائية (هجري نص أم تاريخ)
تُحسم عندها.

## 3.4 ENUM Types (بقيم حقيقية مؤكدة من ArcGIS Domains)

```
deed_status_enum:        محدث / قديم
deed_class_enum:         زراعي / سكني / صناعي
asset_type_enum:         أرض / شقة / عمارة / فيلا / مستودع
qrar_source_enum:        بلدي / مكتب هندسي / بدون
fall_in_enum:             مخطط زراعي / مخطط بلدية
allocation_method_enum:  محدد بدقة / محدد حسب الموقع العام / لم يتم تحديد الموقع
land_transaction_enum:   مباعة / مؤجرة / قيد البيع / خاصة
photo_type_enum:         جوية / أرضية
modification_request_status_enum: pending / sent_to_arcgis / applied / rejected
user_role_enum:          admin / viewer
```

---

# الجزء 4: بيانات GDB الحقيقية المُحللة (Osooli.gdb / GeoJSON)

- **31 سجل قطعة**، layer واحد "Osooli"، كلهم plan واحد (623)، نفس المالك،
  نفس تاريخ الصك (1435-03-08 هجري) — أرض واحدة مقسّمة لـ 31 قطعة (عينة
  اختبار وليست تمثيلية للحجم الكامل)
- Geometry: Polygon (في الـ GeoJSON المُصدّر)، CRS = **EPSG:4326** (مؤكد من
  نطاق الإحداثيات: ~46.34 طول، ~24.70 عرض — منطقة الرياض)
- `Geo_ID` = `{Parcel}-{Plan_No}` مثل `"164-623"`

## 4.1 قواعد التنظيف عند الاستيراد
- `N/S/E/W_Border` و `_2` نسخها → متطابقة 100%، احذف `_2`
- `N/S/W_Dim` و `_2` → متطابقة (اختلاف بسيط واحد في E_Dim، يبدو typo)
- `Area` = `Survey_Area` بالضبط → احذف التكرار، الباقي → `deeds.deed_area`
- `ppp` ≈ `Parcel` (اختلاف واحد) → احذف `ppp`
- `Shape_Area` (PostGIS) ≠ `Area` (الصك) — فروقات 200-2200 م² — **لا تُخزن**
  Shape_Area، تُحسب live عبر `ST_Area`
- حقول 100% NULL في العينة لكن لها Domains معرّفة (جاهزة للتعبئة لاحقاً):
  `Deed_Status`, `Deed_Class`, `Fall_In`, `OwnerType` (→ `asset_type`),
  `Qrar`, `District`, `Land_Trasaction`, `Allocation_Method`, `Location_Photo`

---

# الجزء 5: الخطوات التالية لـ Claude Code (بالترتيب)

1. **Migrations**: حوّل `osooli_schema.sql` كاملاً —
   - migration أول: كل `CREATE TYPE ... AS ENUM` عبر `DB::statement()`
   - migrations تالية: الجداول بترتيب التبعيات (geo hierarchy →
     owners/engineering_offices → parcels → deeds → deed_owners →
     parcel_boundaries → survey_decisions → parcel_photos → users →
     modification_requests → sync_log → audit_logs)
   - `CREATE EXTENSION IF NOT EXISTS postgis;` في migration منفصل أول

2. **Models + العلاقات** (Eloquent):
   - `Parcel`: `belongsTo(Plan)`, `belongsTo(Parcel, 'parent_parcel_id')`
     (self), `hasMany(Parcel, 'parent_parcel_id')`, `hasMany(Deed)`,
     `hasOne(ParcelBoundary)`, `hasMany(SurveyDecision)`,
     `hasMany(ParcelPhoto)`, `hasMany(ModificationRequest)`
   - `Deed`: `belongsTo(Parcel)`, `hasMany(DeedOwner)`,
     `belongsToMany(Owner, 'deed_owners')`
   - `Owner`: `belongsToMany(Deed, 'deed_owners')`,
     `hasMany(ModificationRequest, 'requested_by')`
   - باقي الجداول: علاقات مباشرة بسيطة (belongsTo/hasMany) حسب الـ FKs
     في schema.sql

3. **Spatie Laravel Permission**: تثبيت + migrations + seed لـ roles
   (admin, viewer)

4. **Factories/Seeders**: بيانات تجريبية لكل جدول (مفيد قبل ربط الاستيراد
   الحقيقي)

5. **(لاحقاً)** سكربت Python للاستيراد: GeoJSON/GDB → PostgreSQL، يطبّق
   منطق التجميع (3.3) وتنظيف الحقول (4.1)

6. **إكمال إعداد البيئة**: باقي خطوات Laravel (هيكل المجلدات، Tailwind/Vite،
   فحص نهائي - الخطوات 5-8 من الجزء 2)

---

# الجزء 6: عناصر معلّقة (غير متعلقة بالكود مباشرة، لكن للمتابعة)

- تحديث `Phase1_Implementation_Plan.docx` — توضيح أن mobile = demo فقط،
  web dashboard = admin panel
- إنشاء/تحديث مستند العقد — إضافة ذِكر Flutter
- تحديث المستند التقني النهائي بآخر القرارات (هذا الملف يغطي المحتوى)

---

# الجزء 7: الملفات المرجعية من الجلسات السابقة

- `osooli_schema.sql` — SQL schema كامل (PostgreSQL + PostGIS)
- `osooli_db_erd_v5_final.mermaid` — ERD تفاعلي
- `osooli_db_erd.png` / `osooli_db_erd.svg` — ERD كصورة (للعرض على العميل)
- مستندات SRS/الخطة (Word) من جلسات سابقة - متعلقة بالنطاق والخطة الزمنية
  لا بقاعدة البيانات
