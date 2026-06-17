# صكوكي — منصة إدارة الصكوك والأراضي

> منصة عقارية متكاملة مبنية على **Laravel 11**، **Livewire**، **Alpine.js**، و**PostgreSQL/PostGIS**.

---

## 📋 المتطلبات

| الأداة | الإصدار الأدنى | التنزيل |
|--------|---------------|---------|
| PHP | 8.2+ | https://www.php.net/downloads |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 18+ | https://nodejs.org |
| npm | 8+ | مضمّن مع Node.js |
| Git | Any | https://git-scm.com |
| PostgreSQL | 14+ | https://www.postgresql.org/download |

> **مستخدمو Windows:** فعّل `pdo_pgsql` و`pgsql` في `php.ini` بإزالة `#` من السطرين:
> ```ini
> extension=pdo_pgsql
> extension=pgsql
> ```

---

## 🚀 التثبيت

### 1. استنساخ المستودع

```bash
git clone https://github.com/osooli-tech/sakuki.git
cd sakuki
```

### 2. تثبيت تبعيات PHP

```bash
composer install
```

### 3. تثبيت تبعيات JavaScript

```bash
npm install
```

### 4. إعداد ملف البيئة

```bash
cp .env.example .env
php artisan key:generate
```

### 5. تهيئة `.env`

```env
APP_NAME=Sakuki
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sakuki_db
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password

REDIS_CLIENT=predis
```

### 6. إنشاء قاعدة البيانات

```sql
CREATE DATABASE sakuki_db;
\c sakuki_db
CREATE EXTENSION IF NOT EXISTS postgis;
```

### 7. تشغيل الـ migrations

```bash
php artisan migrate
```

### 8. (اختياري) تشغيل الـ seeders

```bash
php artisan db:seed
```

---

## ▶️ تشغيل المشروع

```bash
php artisan serve
```

المشروع متاح على: **http://127.0.0.1:8000**

```bash
npm run dev
```

---

## 📦 الحزم المثبّتة

### PHP (Composer)

| الحزمة | الإصدار | الغرض |
|--------|---------|-------|
| `laravel/laravel` | 11.x | الإطار الأساسي |
| `livewire/livewire` | ^4.3 | مكوّنات UI تفاعلية |
| `laravel/sanctum` | ^4.3 | مصادقة API |
| `spatie/laravel-permission` | ^6.25 | الأدوار والصلاحيات |
| `predis/predis` | ^3.5 | Redis client |

### JavaScript (npm)

| الحزمة | الغرض |
|--------|-------|
| `alpinejs` | تفاعلية خفيفة |

---

## 🗄️ قاعدة البيانات (pgAdmin 4)

1. افتح pgAdmin 4
2. اتصل بـ `PostgreSQL 16`
3. انتقل إلى: `Databases` → `sakuki_db` → `Schemas` → `public` → `Tables`

---

## 🛠️ أوامر Artisan المفيدة

```bash
php artisan optimize:clear      # مسح الـ cache
php artisan migrate:fresh       # إعادة الـ migrations (يحذف البيانات)
php artisan migrate:fresh --seed
php artisan route:list
php artisan tinker
```

---

## 📁 هيكل المشروع

```
sakuki/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Livewire/
├── config/
├── database/
│   ├── migrations/
│   ├── import/          ← سكربت استيراد GDB → PostGIS
│   └── seeders/
├── resources/
│   ├── views/
│   └── js/
└── routes/
```

---

## 📄 الترخيص

مرخّص بموجب [MIT license](https://opensource.org/licenses/MIT).
