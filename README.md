# 🏠 Osooli — Real Estate Platform

> A modern real estate platform built with **Laravel 11**, **Livewire**, **Alpine.js**, and **PostgreSQL**.

---

## 📋 Requirements

Make sure you have the following installed before starting:

| Tool | Minimum Version | Download |
|------|----------------|----------|
| PHP | 8.2+ | https://www.php.net/downloads |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 18+ | https://nodejs.org |
| npm | 8+ | Comes with Node.js |
| Git | Any | https://git-scm.com |
| PostgreSQL | 14+ | https://www.postgresql.org/download |

> **Windows users (XAMPP):** After installing PostgreSQL, enable the `pdo_pgsql` and `pgsql` extensions in `C:\xampp\php\php.ini` by uncommenting these lines:
> ```ini
> extension=pdo_pgsql
> extension=pgsql
> ```

---

## 🚀 Installation

### 1. Clone the repository

```bash
git clone https://github.com/osooli-tech/osooli.git
cd osooli
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install JavaScript dependencies

```bash
npm install
```

### 4. Set up environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configure your `.env` file

Open `.env` and update the database section:

```env
APP_NAME=Osooli
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=osooli_db
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password

REDIS_CLIENT=predis
```

### 6. Create the PostgreSQL database

Connect to PostgreSQL and run:

```sql
CREATE DATABASE osooli_db;
\c osooli_db
CREATE EXTENSION IF NOT EXISTS postgis;
```

### 7. Run database migrations

```bash
php artisan migrate
```

### 8. (Optional) Seed the database

```bash
php artisan db:seed
```

---

## ▶️ Running the Project

### Start the development server

```bash
php artisan serve
```

The app will be available at: **http://127.0.0.1:8000**

### Compile frontend assets (in a separate terminal)

```bash
npm run dev
```

---

## 📦 Installed Packages

### PHP (Composer)

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/laravel` | 11.x | Core framework |
| `livewire/livewire` | ^4.3 | Reactive UI components |
| `laravel/sanctum` | ^4.3 | API authentication |
| `spatie/laravel-permission` | ^6.25 | Roles & permissions |
| `predis/predis` | ^3.5 | Redis client |

### JavaScript (npm)

| Package | Purpose |
|---------|---------|
| `alpinejs` | Lightweight JS interactivity |

---

## 🗄️ Database Viewer (pgAdmin 4)

If you installed PostgreSQL via the official installer, **pgAdmin 4** is included.

1. Open pgAdmin 4
2. Connect to `PostgreSQL 16` (password: your postgres password)
3. Navigate to: `Databases` → `osooli_db` → `Schemas` → `public` → `Tables`

---

## 🛠️ Useful Artisan Commands

```bash
# Clear all caches
php artisan optimize:clear

# Run migrations fresh (⚠️ deletes all data)
php artisan migrate:fresh

# Run migrations with seeders
php artisan migrate:fresh --seed

# View all routes
php artisan route:list

# Open interactive shell
php artisan tinker
```

---

## 📁 Project Structure

```
osooli/
├── app/
│   ├── Http/Controllers/     # Controllers
│   ├── Models/               # Eloquent models
│   └── Livewire/             # Livewire components
├── config/
│   ├── sanctum.php           # Sanctum config
│   └── permission.php        # Spatie permission config
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── views/                # Blade templates
│   └── js/                   # JavaScript files
├── routes/
│   ├── web.php               # Web routes
│   └── api.php               # API routes
└── .env.example              # Environment template
```

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit your changes: `git commit -m 'Add your feature'`
4. Push to the branch: `git push origin feature/your-feature`
5. Open a Pull Request

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
