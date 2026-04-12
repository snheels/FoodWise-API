# FoodWise Backend API

## Deskripsi
FoodWise Backend adalah REST API yang digunakan untuk mengelola data makanan, termasuk pencatatan stok, tanggal kedaluwarsa, serta integrasi dengan Google Calendar untuk pengingat.

---

## Fitur Utama
- CRUD data makanan
- Fitur "Expiring Soon"
- Dashboard data makanan
- Integrasi Google Calendar API untuk reminder

---

## Teknologi
- Laravel
- MySQL
- Google Calendar API

---

## Cara Menjalankan Project

### 1. Clone Repository
```bash
git clone <link-repo-backend>
cd FoodWise-Backend
```

## 2. Install Dependency
   ```bash
   composer install
   ```
   
## 4. Setup Environment
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
## 6. Konfigurasi Database

Edit file .env:

DB_DATABASE=foodwise
DB_USERNAME=root
DB_PASSWORD=
DB_PORT=3308

## 5. Migrasi Database
php artisan migrate

## 7. Jalankan Server
php artisan serve

Server akan berjalan di:
http://127.0.0.1:8000

---

## Endpoint API
GET /api/foods
POST /api/foods
GET /api/foods/dashboard
GET /api/foods/expiring-soon

---

## Konfigurasi Tambahan
Pastikan Google Calendar API sudah di-setup
Tambahkan credential di .env jika diperlukan

---

## Tujuan
Backend ini dibuat untuk mendukung pengelolaan makanan dan membantu pengguna mengurangi food waste melalui sistem pencatatan dan pengingat otomatis.
