# Osooli (أصولي) — DB Schema Handoff لـ Laravel Implementation

> ملف مرجعي شامل لكل القرارات النهائية حول قاعدة البيانات. الهدف: تعطيه
> لـ Claude Code داخل مجلد مشروع Laravel ليبدأ كتابة الـ migrations
> والـ models بناءً عليه مباشرة، بدون إعادة شرح السياق.

---

## 1. التقنيات المؤكدة (لا حاجة لمراجعتها)

- **Backend**: Laravel 11 + Blade + Livewire 3 + Alpine.js (لا Next.js، لا Filament)
- **Database**: PostgreSQL 16 + PostGIS 3.5
- **GIS**: GeoServer (WMS/WFS) + Mapbox GL JS (أساسي) / Leaflet (احتياطي)
- **Auth**: Laravel Sanctum (لـ Flutter Phase 2) + Spatie Laravel Permission (RBAC: admin/viewer)
- **SMS/OTP**: Unifonic
- **لا Docker** — تثبيت مباشر على Windows
- **لا OCR** في أي مكان

---

## 2. ملف Schema الجاهز

**`osooli_schema.sql`** (مرفق بجانب هذا الملف) — SQL كامل وصالح للتشغيل مباشرة
على `psql`: PostGIS extension + 10 ENUM types + 17 جدول + كل الفهارس والعلاقات.

**الخطوة المطلوبة من Claude Code**: تحويل هذا الملف إلى Laravel migrations
(ترتيب الجداول في `osooli_schema.sql` يحترم تبعيات الـ Foreign Keys —
نفّذ الـ migrations بنفس الترتيب). الـ ENUM types تحتاج تُنشأ بـ
`DB::statement("CREATE TYPE ... AS ENUM (...)")` داخل migration منفصل
قبل migration الجداول التي تستخدمها.

---

## 3. ملخص التصميم النهائي (17 جدول)

### أ. الهيكل الجغرافي (owned by us)
```
countries → regions → cities → districts → plans
```
- `cities` تمثل **كل بلدة/مدينة بحجمها** (كبيرة أو صغيرة، مثل الخرج/الدرعية)
  كصفوف مستقلة تحت `region` واحدة — لا يوجد جدول "محافظات" منفصل.
- `plans.district_id` يحدد المدينة/المنطقة/الدولة عبر joins تلقائياً.

### ب. النواة (synced from ArcGIS + owned by us)

| الجدول | الوصف | ملاحظة مهمة |
|---|---|---|
| `owners` | الملاك (بيانات تواصل) | `national_id` فريد لو موجود |
| `engineering_offices` | المكاتب الهندسية | owned by us |
| `parcels` | القطعة الجغرافية | `geom MultiPolygon EPSG:4326`، `geo_id` فريد = مفتاح المزامنة، `parent_parcel_id` ذاتي العلاقة (شقق داخل عمارة) |
| `deeds` | الصكوك | 1:N من parcels (تاريخ صكوك)، `deed_area` = مساحة الصك |
| `deed_owners` | **جدول وسيط** ملكية مشتركة | كل صك ممكن له أكثر من مالك. `ownership_share` غالباً NULL (نص داخل الصك، OCR خارج النطاق) |
| `parcel_boundaries` | الحدود والأبعاد | 1:1 مع parcels، `measured_area` = المساحة حسب الطبيعة (مختلفة عن deed_area)، `survey_date` nullable (لم يُضف من ArcGIS بعد) |
| `survey_decisions` | القرارات المساحية | 1:N (قطعة ممكن لها أكثر من قرار بمرور الوقت) |
| `parcel_photos` | صور القطعة | 1:N، `photo_url` = رابط Drive |
| `modification_requests` | طلبات تعديل من الملاك | owned by us، تُرسل يدوياً لـ Al-Esnad |

### ج. النظام (owned by us)

| الجدول | الوصف |
|---|---|
| `users` | مستخدمو لوحة التحكم (admin/viewer) — **مختلفين عن owners** |
| `audit_logs` | سجل تدقيق ثابت (دخول/تنزيل/تصدير) |
| `sync_log` | سجل عمليات المزامنة من ArcGIS |

---

## 4. أهم القرارات الهيكلية (السياق الكامل)

1. **`parent_parcel_id`** (ذاتي العلاقة على `parcels`): صف بدون parent =
   أرض/عمارة أصلية (من ArcGIS، له `geom`). صف بـ parent معبأ = شقة
   داخل تلك العمارة (بدون `geom`، يُدخل يدوياً من فريق أصولي — ليس من ArcGIS).

2. **`deed_owners`** (ملكية مشتركة): تأكد من العميل أن ArcGIS يمثل كل
   مالك كسجل (Feature) مستقل بنفس `Geo_ID` + `Deed_No` (نفس geometry).
   **سكربت الاستيراد لازم يجمّع (`GROUP BY Geo_ID, Deed_No`)**: مجموعة
   = مالك واحد → صف `deed_owners` واحد. مجموعة بأكثر من سجل (نفس
   Geo_ID/Deed_No، Woner_ID مختلف) → صف واحد في `parcels`/`deeds` +
   عدة صفوف `deed_owners`. **تكرار Geo_ID ليس خطأ بالضرورة** — فقط لو
   اختلفت الـ geometry أو Deed_No بين السجلات المتكررة فهذا يحتاج مراجعة.

3. **المساحات الثلاثة**: `deeds.deed_area` (مساحة الصك القانونية) ≠
   `parcel_boundaries.measured_area` (محسوبة من الأبعاد N/S/E/W، "حسب
   الطبيعة") ≠ `ST_Area(parcels.geom)` (محسوبة لحظياً من PostGIS، غير مخزنة).

4. **`deed_date_hijri`**: نص (`varchar(10)`) مثل `'1435-03-08'` — ليس
   تاريخ Gregorian حقيقي. القيمة الأصلية في ArcGIS هي epoch مللي ثانية
   سالب يمثل أرقام Y-M-D هجرية كأنها Gregorian — يجب استخراج الأرقام
   فقط بدون تحويل تقويمي حقيقي عند الاستيراد.

5. **ENUMs بقيم حقيقية مؤكدة من ArcGIS Domains**:
   - `land_transaction_enum`: مباعة / مؤجرة / قيد البيع / خاصة
   - `qrar_source_enum`: بلدي / مكتب هندسي / بدون
   - `allocation_method_enum`: محدد بدقة / محدد حسب الموقع العام / لم يتم تحديد الموقع
   - `deed_class_enum`: زراعي / سكني / صناعي
   - `deed_status_enum`: محدث / قديم
   - `fall_in_enum`: مخطط زراعي / مخطط بلدية
   - `asset_type_enum`: أرض / شقة / عمارة / فيلا / مستودع

6. **`source_gdb_id`** موجود في `parcels` (OBJECTID تمثيلي) **و**
   `deed_owners` (OBJECTID لكل سجل مالك على حدة) — للتتبع الدقيق أثناء
   إعادة المزامنة.

---

## 5. خطوات Laravel التالية (المقترحة لـ Claude Code)

1. **Migrations**: تحويل `osooli_schema.sql` كاملاً — enum types أولاً،
   ثم الجداول بالترتيب (الهيكل الجغرافي → owners/engineering_offices →
   parcels → deeds → deed_owners → parcel_boundaries → survey_decisions
   → parcel_photos → users → modification_requests → sync_log → audit_logs)
2. **Models + العلاقات**: كل جدول = Model، مع علاقات Eloquent مطابقة
   (`hasMany`, `belongsTo`, `belongsToMany` عبر `deed_owners` لـ
   parcels↔owners، self-referencing لـ parcels)
3. **Spatie Permission**: تثبيت + تشغيل migrations الخاصة به (roles:
   admin/viewer)
4. **Factories/Seeders**: بيانات تجريبية لكل جدول (مفيدة للتطوير قبل
   ربط السكربت الحقيقي)
5. (لاحقاً) **سكربت الاستيراد Python**: GeoJSON/GDB → PostgreSQL، يطبّق
   منطق التجميع في البند 4.2

---

## 6. أسئلة مفتوحة (لا تعيق العمل، لكن للمتابعة مع Al-Esnad)

- `survey_date` (تاريخ الرفع المساحي): العميل سيضيفه لـ ArcGIS لاحقاً،
  الحقل موجود وnullable حالياً
- صيغة `survey_date` النهائية (هجري نص أم تاريخ) تُحسم لما يضيفها العميل

---

## 7. ملفات مرجعية من هذه الجلسة

- `osooli_schema.sql` — SQL schema كامل (هذا الملف)
- `osooli_db_erd_v5_final.mermaid` — ERD تفاعلي (يفتح كرسم في claude.ai)
- `osooli_db_erd.png` / `osooli_db_erd.svg` — ERD كصورة للعرض على العميل
- `osooli_project_context.md` — السياق الأصلي الكامل للمشروع (المتطلبات،
  الـ stack، الـ GDB الحقيقي، إلخ) — **اقرأه أولاً لو تحتاج الخلفية الكاملة**
