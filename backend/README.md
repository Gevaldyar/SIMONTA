
# SIMONTA Backend

Backend SIMONTA dibangun menggunakan Laravel 13 dan berfungsi sebagai REST API untuk aplikasi monitoring website OPD.

## Tanggung Jawab Backend

Backend menangani:

- Autentikasi administrator menggunakan Laravel Sanctum.
- Manajemen data OPD dan website.
- Monitoring website secara manual dan otomatis.
- Identifikasi teknologi website.
- Deteksi perubahan teknologi.
- Pemeriksaan registrasi domain dan sertifikat SSL.
- Security Assessment dan Security Headers Audit.
- Notifikasi sistem.
- Ekspor laporan monitoring.
- Pengelolaan profil administrator.

## Teknologi

- PHP 8.5
- Laravel 13
- Laravel Sanctum
- MySQL 8
- Laravel Queue
- Laravel Scheduler

## Persyaratan

- PHP 8.3 atau lebih baru.
- Composer.
- MySQL 8.
- Ekstensi PHP yang diperlukan oleh Laravel dan Composer.

## Instalasi

Jalankan dari direktori `backend`.

### 1. Instal dependency

```bash
composer install
```

### 2. Salin environment

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 3. Buat database MySQL

```sql
CREATE DATABASE simonta_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 4. Konfigurasi `.env`

Sesuaikan DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, dan DB_PASSWORD.

Pastikan konfigurasi Sanctum sesuai dengan alamat frontend.

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Jalankan migration

```bash
php artisan migrate
```

### 7. Seed katalog teknologi

```bash
php artisan db:seed --class=TechnologyCatalogSeeder
```

### 8. Buat administrator

```bash
php artisan simonta:make-admin
```

Password administrator minimal 12 karakter.

### 9. Jalankan server

```bash
php artisan serve
```

Backend berjalan pada `http://localhost:8000`.

## Queue dan Scheduler

SIMONTA menggunakan database queue untuk memproses pekerjaan monitoring.

Jalankan queue worker:

```bash
php artisan queue:work database -v
```

Jalankan scheduler lokal:

```bash
php artisan schedule:work
```

Keduanya harus tetap berjalan selama pengujian monitoring otomatis.

Untuk produksi, gunakan process manager seperti Supervisor atau systemd untuk queue worker, serta cron untuk menjalankan Laravel Scheduler sesuai konfigurasi server.

## Pengujian

Jalankan seluruh pengujian:

```bash
php artisan test
```

Pengujian mencakup autentikasi, API, monitoring, validasi URL, deteksi teknologi, perubahan teknologi, Domain & SSL, notifikasi, laporan, dan modul keamanan.

## Struktur Folder Utama

```text
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Jobs/
│   ├── Models/
│   ├── Notifications/
│   └── Services/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
├── tests/
├── .env.example
└── composer.json
```

## Catatan Keamanan

- Jangan mengunggah `.env` ke repository.
- Jangan menonaktifkan validasi TLS.
- Pemeriksaan website harus tetap menggunakan validasi URL dan pembatasan alamat IP internal.
- Gunakan HTTPS, `APP_DEBUG=false`, dan konfigurasi CORS yang sesuai pada produksi.
