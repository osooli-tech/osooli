# Osooli Mobile API — عقد الواجهة (v1)

> **الحالة:** مقترح للمراجعة — لم يُنفَّذ بعد.
> **الغرض:** تثبيت شكل الطلبات والاستجابات ليبدأ فريق الموبايل بالتوازي مع تنفيذ الـ backend.
> **آخر تحديث:** 2026-07-23

---

## 1. أساسيات

| البند | القيمة |
|---|---|
| Base URL (اختبار) | `https://lightgreen-snake-236372.hostingersite.com/api/v1` |
| المصادقة | Laravel Sanctum — Bearer Token |
| الترميز | UTF-8، JSON |
| اللغة | ترسل `Accept-Language: ar` أو `en` (الافتراضي `ar`) |
| التواريخ الميلادية | ISO-8601 مثل `2026-07-23T10:30:00Z` |
| تواريخ الصكوك | **هجرية كنص** مثل `1442-04-21` — لا تُحوَّل ولا تُعالج كتاريخ ميلادي |
| الإحداثيات | WGS 84 (EPSG:4326) |

### الترويسات المطلوبة

```http
Accept: application/json
Content-Type: application/json
Accept-Language: ar
Authorization: Bearer {token}      // لكل المسارات ما عدا auth/*
```

### قواعد حاكمة

1. **كل مالك يرى ممتلكاته فقط.** أي معرّف (id) يُرسل من التطبيق يُتحقَّق من ملكيته أولًا؛ محاولة الوصول لقطعة غير مملوكة تُرجع `404` (لا `403`، حتى لا نكشف وجودها).
2. **الأسعار لا تُرجَع إطلاقًا** في أي استجابة (قرار العميل).
3. **القطعة قد تكون ملكية مشتركة** — لذلك `owners` مصفوفة دائمًا، وليست كائنًا واحدًا.
4. **القطعة قد يكون لها أكثر من صك** (تاريخ الصكوك) — `deeds` مصفوفة، و`current_deed` هو الأحدث.

---

## 2. شكل الاستجابة الموحّد

### نجاح — عنصر واحد
```json
{
  "data": { }
}
```

### نجاح — قائمة مع ترقيم صفحات
```json
{
  "data": [ ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 137,
    "last_page": 7
  }
}
```

### خطأ تحقُّق — 422
```json
{
  "message": "البيانات المُدخلة غير صحيحة.",
  "errors": {
    "phone": ["رقم الجوال مطلوب."]
  }
}
```

### أخطاء أخرى
```json
{ "message": "غير مصرّح لك بالوصول." }
```

| الكود | المعنى |
|:---:|---|
| 200 | نجاح |
| 201 | أُنشئ |
| 401 | توكن مفقود/منتهي → أعد تسجيل الدخول |
| 404 | غير موجود **أو** غير مملوك للمستخدم |
| 422 | خطأ في المدخلات |
| 429 | تجاوز حد المحاولات (OTP) |
| 500 | خطأ في الخادم |

---

## 3. المصادقة

### 3.1 طلب رمز التحقق

```http
POST /auth/request-otp
```
```json
{ "phone": "0512345678" }
```

**الاستجابة 200**
```json
{
  "data": {
    "message": "تم إرسال رمز التحقق",
    "expires_in": 300
  }
}
```

> **مرحلة الاختبار:** الرمز ثابت `6666` لكل الأرقام المسجَّلة. سيُستبدل برمز عشوائي عبر SMS لاحقًا — **بدون تغيير في شكل الطلب أو الاستجابة**.

**أخطاء**
| الحالة | الكود | الجسم |
|---|:---:|---|
| رقم غير مسجّل | 404 | `{"message": "رقم الجوال غير مسجّل"}` |
| محاولات كثيرة | 429 | `{"message": "حاول بعد قليل"}` |

### 3.2 التحقق وتسجيل الدخول

```http
POST /auth/verify-otp
```
```json
{ "phone": "0512345678", "otp": "6666" }
```

**الاستجابة 200**
```json
{
  "data": {
    "token": "1|AbCdEf123456...",
    "owner": {
      "id": 1,
      "name": "عبدالعزيز خالد محمد بن خميس",
      "national_id": "1001227220",
      "phone": "0512345678",
      "email": null
    }
  }
}
```

> التوكن يُخزَّن بأمان في التطبيق ويُرسل في كل طلب لاحق. لا انتهاء تلقائي حاليًا؛ `401` تعني أعد الدخول.

**خطأ 422** — رمز خاطئ
```json
{ "message": "رمز التحقق غير صحيح", "errors": { "otp": ["رمز التحقق غير صحيح"] } }
```

### 3.3 بيانات المستخدم الحالي

```http
GET /me
```
```json
{
  "data": {
    "id": 1,
    "name": "عبدالعزيز خالد محمد بن خميس",
    "national_id": "1001227220",
    "phone": "0512345678",
    "email": null,
    "stats": { "parcels_count": 2, "deeds_count": 2, "documents_count": 2 }
  }
}
```

### 3.4 تسجيل الخروج

```http
POST /auth/logout
```
```json
{ "data": { "message": "تم تسجيل الخروج" } }
```

---

## 4. الشاشة الرئيسية

```http
GET /dashboard
```

**الاستجابة 200**
```json
{
  "data": {
    "greeting_name": "عبدالعزيز",
    "stats": {
      "parcels_total": 2,
      "deeds_active": 2,
      "deeds_expired": 0,
      "parcels_without_deed": 0,
      "survey_decisions_total": 2,
      "area_total_sqm": 20301.5,
      "cities_count": 1,
      "documents_count": 2
    },
    "by_city": [
      { "name": "الدرعية", "parcels_count": 2 }
    ],
    "by_district": [
      { "name": "العمارية", "parcels_count": 2 }
    ],
    "data_completeness": {
      "percentage": 84,
      "breakdown": [
        { "label": "مكتملة", "count": 1 },
        { "label": "ناقصة", "count": 1 }
      ]
    },
    "unread_notifications": 0
  }
}
```

> **ملاحظة:** الأرقام الكبيرة في التصميم (1,245 قطعة / 12 مدينة) كانت بيانات نموذج فقط. الـ API يُرجع أرقام المالك الحقيقية — وقد تكون قطعة أو اثنتين.
>
> `deeds_active` = حالة الصك «محدث» · `deeds_expired` = «قديم».

---

## 5. القطع (ممتلكاتي)

### 5.1 قائمة القطع

```http
GET /parcels?page=1&per_page=20&search=&city=&district=&asset_type=
```

| المعامل | النوع | الوصف |
|---|---|---|
| `page` | int | رقم الصفحة (افتراضي 1) |
| `per_page` | int | 1–100 (افتراضي 20) |
| `search` | string | رقم قطعة / رقم صك / رقم مخطط |
| `city` | string | اسم المدينة |
| `district` | string | اسم الحي |
| `asset_type` | enum | `أرض` `شقة` `عمارة` `فيلا` `مستودع` |

**الاستجابة 200**
```json
{
  "data": [
    {
      "id": 16,
      "parcel_no": "91",
      "geo_id": "91-25",
      "asset_type": "أرض",
      "plan_no": "25",
      "city": "الدرعية",
      "district": "العمارية",
      "area_sqm": 10150.48,
      "current_deed": {
        "id": 42,
        "deed_no": "311608002898",
        "deed_date_hijri": "1442-04-21",
        "deed_status": "محدث"
      },
      "documents_count": 1,
      "centroid": { "lat": 24.703288, "lng": 46.344857 }
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 2, "last_page": 1 }
}
```

### 5.2 تفاصيل قطعة

```http
GET /parcels/{id}
```

**الاستجابة 200**
```json
{
  "data": {
    "id": 16,
    "parcel_no": "91",
    "geo_id": "91-25",
    "asset_type": "أرض",
    "land_transaction": "خاصة",
    "allocation_method": "محدد بدقة",
    "fall_in": "مخطط زراعي",
    "plan_no": "25",
    "city": "الدرعية",
    "district": "العمارية",

    "centroid": { "lat": 24.703288, "lng": 46.344857 },
    "corners": [
      { "lat": 24.709389, "lng": 46.354623 },
      { "lat": 24.708869, "lng": 46.356064 },
      { "lat": 24.707390, "lng": 46.355123 },
      { "lat": 24.707423, "lng": 46.353473 }
    ],
    "geometry": {
      "type": "MultiPolygon",
      "coordinates": [[[[46.3536, 24.7074], [46.3546, 24.7094]]]]
    },

    "owners": [
      { "id": 1, "name": "عبدالعزيز خالد محمد بن خميس", "national_id": "1001227220", "ownership_share": null }
    ],

    "deeds": [
      {
        "id": 42,
        "deed_no": "311608002898",
        "deed_date_hijri": "1442-04-21",
        "deed_area_sqm": 10150.48,
        "deed_status": "محدث",
        "deed_class": "زراعي",
        "is_current": true
      }
    ],

    "boundary": {
      "north": { "description": "شارع عرض 15 متر", "length_m": 50.00 },
      "south": { "description": "قطعة رقم 409", "length_m": 50.00 },
      "east":  { "description": "قطعة رقم 400", "length_m": 60.00 },
      "west":  { "description": "شارع عرض 20 متر", "length_m": 60.00 },
      "measured_area_sqm": null,
      "matches_deed": null,
      "engineering_office": "مكتب الإسناد العالمي للاستشارات الهندسية"
    },

    "survey_decision": {
      "qrar_no": null,
      "report_no": null,
      "qrar_source": "مكتب هندسي",
      "folder": "قرارات"
    },

    "documents": [
      {
        "id": 7,
        "type": "صك",
        "file_type": "pdf",
        "size_bytes": 3570216,
        "download_url": "https://.../api/v1/documents/7/download",
        "created_at": "2026-07-20T14:00:00Z"
      }
    ]
  }
}
```

> **`geometry`** حجمها كبير. لعرض الخريطة داخل التفاصيل استخدم `geometry`؛ ولرسم الحدود النصية استخدم `boundary`.
> **404** إذا كانت القطعة غير مملوكة للمستخدم.

### 5.3 قطع الخريطة (GeoJSON)

```http
GET /parcels/map
```

مُحسَّن لعرض الخريطة — GeoJSON قياسي يُحمَّل مباشرة في Mapbox/Google Maps.

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "MultiPolygon", "coordinates": [] },
      "properties": {
        "id": 16,
        "parcel_no": "91",
        "asset_type": "أرض",
        "district": "العمارية",
        "deed_no": "311608002898",
        "deed_status": "محدث",
        "area_sqm": 10150.48
      }
    }
  ]
}
```

---

## 6. الصكوك

### 6.1 سجل الصكوك

```http
GET /deeds?page=1&per_page=20&status=all&search=
```

| `status` | المعنى |
|---|---|
| `all` | الكل (افتراضي) |
| `active` | محدث |
| `expired` | قديم |

`search` يطابق: رقم الصك، رقم القطعة، رقم المخطط، المدينة، الحي.

**الاستجابة 200**
```json
{
  "data": [
    {
      "id": 42,
      "deed_no": "311608002898",
      "deed_date_hijri": "1442-04-21",
      "deed_area_sqm": 10150.48,
      "deed_status": "محدث",
      "deed_class": "زراعي",
      "parcel": {
        "id": 16,
        "parcel_no": "91",
        "plan_no": "25",
        "city": "الدرعية",
        "district": "العمارية"
      },
      "document": {
        "id": 7,
        "download_url": "https://.../api/v1/documents/7/download"
      }
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 2, "last_page": 1 }
}
```

> فلاتر التصميم «قيد المعالجة» و«جديد» **غير مدعومة في v1** — قاعدة البيانات تعرف حالتين فقط (محدث/قديم). تحتاج قرارًا من العميل قبل إضافتها.
>
> `document` يكون `null` إذا لم يُرفَق ملف الصك.

---

## 7. المستندات

### 7.1 مستندات قطعة

```http
GET /parcels/{id}/documents
```
```json
{
  "data": [
    {
      "id": 7,
      "type": "صك",
      "file_type": "pdf",
      "size_bytes": 3570216,
      "download_url": "https://.../api/v1/documents/7/download",
      "created_at": "2026-07-20T14:00:00Z"
    },
    {
      "id": 8,
      "type": "كروكي مساحي",
      "file_type": "pdf",
      "size_bytes": 359880,
      "download_url": "https://.../api/v1/documents/8/download",
      "created_at": "2026-07-04T12:00:00Z"
    }
  ]
}
```

**أنواع المستندات:** `صك` · `كروكي مساحي` · `جوية` · `أرضية`

### 7.2 تنزيل / عرض مستند

```http
GET /documents/{id}/download
```

**302 Redirect** إلى رابط الملف. كل عملية تُسجَّل في سجل التدقيق.

- **العرض داخل التطبيق:** افتح الرابط في `WebView` / `PDFView`
- **التنزيل:** حمّل الرابط مباشرة
- **404** إذا كان المستند لقطعة غير مملوكة

---

## 8. البحث

```http
GET /search?q=311608&type=deed
```

| `type` | يبحث في |
|---|---|
| `deed` | رقم الصك (افتراضي) |
| `parcel` | رقم القطعة |
| `plan` | رقم المخطط |
| `owner` | اسم المالك |

**الاستجابة 200**
```json
{
  "data": [
    {
      "type": "parcel",
      "id": 16,
      "title": "قطعة 91",
      "subtitle": "صك 311608002898 · الدرعية / العمارية",
      "badge": "محدث"
    }
  ],
  "meta": { "total": 1 }
}
```

> **مهم:** البحث بـ `owner` مقيَّد بممتلكات المستخدم — لا يكشف ملّاكًا آخرين.
> **«عمليات البحث الأخيرة»** في التصميم تُخزَّن **محليًا في التطبيق** (لا endpoint لها في v1).

---

## 9. طلبات التعديل

### 9.1 قائمة طلباتي

```http
GET /modification-requests?page=1
```
```json
{
  "data": [
    {
      "id": 3,
      "parcel": { "id": 16, "parcel_no": "91" },
      "field_name": "asset_type",
      "field_label": "نوع الأصل",
      "old_value": "أرض",
      "new_value": "فيلا",
      "notes": "تم البناء على الأرض",
      "status": "pending",
      "status_label": "قيد المراجعة",
      "created_at": "2026-07-23T10:00:00Z",
      "resolved_at": null
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

| `status` | `status_label` |
|---|---|
| `pending` | قيد المراجعة |
| `sent_to_arcgis` | أُرسل للتحديث |
| `applied` | تم التطبيق |
| `rejected` | مرفوض |

### 9.2 إنشاء طلب تعديل

```http
POST /modification-requests
```
```json
{
  "parcel_id": 16,
  "field_name": "asset_type",
  "new_value": "فيلا",
  "notes": "تم البناء على الأرض"
}
```

**201**
```json
{
  "data": {
    "id": 4,
    "status": "pending",
    "status_label": "قيد المراجعة",
    "message": "تم إرسال طلب التعديل"
  }
}
```

**الحقول القابلة للتعديل** (`field_name`):
`asset_type` · `land_transaction` · `allocation_method` · `fall_in` · `deed_status` · `deed_class`

> `422` إذا كان `parcel_id` غير مملوك أو `field_name` غير مسموح.

---

## 10. الإشعارات

```http
GET  /notifications?page=1
POST /notifications/{id}/read
```
```json
{
  "data": [
    {
      "id": 12,
      "title": "تم تحديث صك القطعة 91",
      "body": "حالة الصك أصبحت: محدث",
      "type": "deed_updated",
      "is_read": false,
      "created_at": "2026-07-23T09:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

> ⚠️ **غير موجود في قاعدة البيانات حاليًا** — يحتاج جدولًا جديدًا. مؤجَّل بعد v1 إلا إذا كان مطلوبًا للإطلاق.
> إشعارات Push (FCM) غير مشمولة في v1.

---

## 11. الملف الشخصي

### 11.1 تحديث البيانات

```http
PATCH /me
```
```json
{ "email": "owner@example.com", "whatsapp": "0512345678" }
```

> `name` و`national_id` و`phone` **غير قابلة للتعديل** من التطبيق (بيانات موثّقة رسميًا).

**200** — يُرجع نفس شكل `GET /me`

---

## 12. جدول مرجعي: الشاشة ← الـ endpoints

| الشاشة | الـ endpoints |
|---|---|
| تسجيل الدخول | `POST /auth/request-otp` → `POST /auth/verify-otp` |
| الرئيسية | `GET /dashboard` |
| سجل الصكوك | `GET /deeds` + `GET /documents/{id}/download` |
| البحث | `GET /search` |
| الخريطة | `GET /parcels/map` |
| تفاصيل القطعة | `GET /parcels/{id}` |
| الحساب | `GET /me` · `PATCH /me` · `POST /auth/logout` |
| طلبات التعديل | `GET/POST /modification-requests` |

---

## 13. القيم المسموحة (Enums)

| الحقل | القيم |
|---|---|
| `asset_type` | أرض · شقة · عمارة · فيلا · مستودع |
| `land_transaction` | مباعة · مؤجرة · قيد البيع · خاصة |
| `deed_status` | محدث · قديم |
| `deed_class` | زراعي · سكني · صناعي |
| `qrar_source` | بلدي · مكتب هندسي · بدون |
| `allocation_method` | محدد بدقة · محدد حسب الموقع العام · لم يتم تحديد الموقع |
| `fall_in` | مخطط زراعي · مخطط بلدية |
| `document.type` | صك · كروكي مساحي · جوية · أرضية |

> القيم تُرسل وتُستقبل **بالعربية كما هي** (هكذا تُخزَّن في قاعدة البيانات).
> للعرض بالإنجليزية، يتولّى التطبيق الترجمة.

---

## 14. نقاط مفتوحة تحتاج قرارًا

| # | البند | الأثر |
|:---:|---|---|
| 1 | **أرقام جوالات الملاك فارغة (0 من 81)** | لا يمكن لأي مالك تسجيل الدخول. يلزم تزويدنا بالأرقام من العميل. للاختبار سيُضاف رقم تجريبي لمالك واحد. |
| 2 | **فلاتر «قيد المعالجة» و«جديد»** في سجل الصكوك | غير مدعومة — قاعدة البيانات تعرف حالتين فقط. تحتاج تعريفًا من العميل. |
| 3 | **الإشعارات** | تحتاج جدولًا جديدًا. مؤجَّلة إلا إذا كانت مطلوبة للإطلاق. |
| 4 | **نسبة اكتمال البيانات** في الرئيسية | تحتاج تعريفًا: ما الحقول التي تُحسب؟ |
| 5 | **زر «التصدير»** في التصميم | لم يُحدَّد شكله (PDF/Excel؟ وما محتواه؟) — غير مشمول في v1. |

---

## 15. للاختبار

بعد التنفيذ:

```bash
# 1) طلب رمز
curl -X POST 'https://.../api/v1/auth/request-otp' \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"0500000000"}'

# 2) التحقق والحصول على توكن
curl -X POST 'https://.../api/v1/auth/verify-otp' \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"0500000000","otp":"6666"}'

# 3) استخدام التوكن
curl 'https://.../api/v1/parcels' \
  -H 'Accept: application/json' -H 'Authorization: Bearer {token}'
```
