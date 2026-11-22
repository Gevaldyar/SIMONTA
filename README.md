
# SIMONTA
### Sistem Monitoring Tautan Website OPD

<p align="center">
  <strong>Diskominfo Kota Tasikmalaya</strong>
</p>

<p align="center">
  Aplikasi monitoring terpusat untuk memantau ketersediaan,
  teknologi, domain, dan aspek keamanan website Organisasi Perangkat Daerah.
</p>

---

## Tentang SIMONTA

SIMONTA (Sistem Monitoring Tautan Website OPD) merupakan aplikasi berbasis web yang dikembangkan untuk membantu administrator Dinas Komunikasi dan Informatika Kota Tasikmalaya dalam melakukan pemantauan website Organisasi Perangkat Daerah (OPD) secara terpusat.

Aplikasi ini menyediakan mekanisme monitoring otomatis, identifikasi teknologi website, pencatatan perubahan teknologi, pemeriksaan domain dan sertifikat SSL, serta analisis keamanan website.

**Status proyek:** Fitur utama telah diimplementasikan dan diuji pada lingkungan pengembangan lokal. Persiapan deployment produksi dan koordinasi infrastruktur masih menjadi tahap lanjutan.

## Fitur Utama

| Modul | Deskripsi |
|---|---|
| Authentication | Login dan logout administrator menggunakan Laravel Sanctum |
| Dashboard | Ringkasan kondisi website, aktivitas monitoring, dan statistik teknologi |
| OPD Management | Pengelolaan data Organisasi Perangkat Daerah |
| Website Registry | Pengelolaan daftar website yang menjadi target monitoring |
| Monitoring Center | Pemindaian website secara manual dan otomatis |
| Technology Detection | Identifikasi teknologi melalui HTTP response, HTML, dan fingerprint |
| Technology Changes | Deteksi penambahan, penghapusan, dan perubahan versi teknologi |
| Inspection History | Riwayat hasil pemindaian beserta detail pemeriksaan |
| Domain & SSL Monitoring | Pemeriksaan registrasi domain dan sertifikat TLS/SSL |
| Security Monitoring | Analisis heuristik terhadap indikator keamanan pada HTML |
| Security Headers Audit | Pemeriksaan konfigurasi HTTP security headers |
| Notifications | Pemberitahuan terkait aktivitas monitoring dan temuan sistem |
| Reports | Ekspor laporan hasil monitoring dalam format CSV |
| Settings | Pengelolaan profil dan password administrator |

## Teknologi yang Digunakan

### Backend
- PHP 8.5
- Laravel 13
- Laravel Sanctum
- MySQL 8
- Laravel Queue
- Laravel Scheduler

### Frontend
- React 19
- Vite 8
- Tailwind CSS 4
- Axios
- React Router

### Development Tools
- Composer
- Node.js
- npm
- Git
- Laragon
- Visual Studio Code

## Arsitektur Aplikasi

SIMONTA menggunakan arsitektur frontend dan backend yang terpisah.

```text
SIMONTA
│
├── frontend/
│   ├── React
│   ├── Vite
│   ├── Tailwind CSS
│   ├── React Router
│   └── Axios
│
└── backend/
    ├── Laravel
    ├── REST API
    ├── Laravel Sanctum
    ├── Queue & Scheduler
    └── MySQL
```

Frontend bertanggung jawab terhadap antarmuka administrator dan komunikasi dengan REST API. Backend menangani autentikasi, proses bisnis, monitoring, identifikasi teknologi, pemeriksaan keamanan, serta pengelolaan database.

## Mekanisme Monitoring

SIMONTA mendukung interval monitoring website yang dapat dikonfigurasi:

- 1 menit
- 5 menit
- 10 menit
- 30 menit

Laravel Scheduler bertugas mengatur pemeriksaan website dan pemulihan proses pemindaian yang mengalami kendala. Pekerjaan monitoring diproses menggunakan Laravel Queue dengan database sebagai backend antrean.

Pemeriksaan domain dan sertifikat SSL juga dijadwalkan secara berkala.

## Identifikasi Teknologi

Identifikasi teknologi dilakukan melalui beberapa sumber informasi, antara lain:

- HTTP response headers.
- HTML source dan elemen halaman.
- Technology fingerprint dan signature.
- Bukti atau evidence dari proses identifikasi.

Sistem menyimpan teknologi yang terdeteksi beserta informasi versi apabila tersedia, confidence, dan evidence.

SIMONTA juga membandingkan hasil pemindaian untuk mengetahui perubahan teknologi pada website.

## Domain, SSL, dan Keamanan

### Domain & SSL Monitoring
Pemeriksaan mencakup:
- Informasi registrasi domain melalui RDAP.
- Registrar dan tanggal kedaluwarsa domain.
- Nameserver.
- Informasi penerbit sertifikat SSL.
- Masa berlaku sertifikat.
- Validasi sertifikat dan hostname.

Verifikasi sertifikat TLS tetap diwajibkan. Kegagalan verifikasi dicatat sebagai temuan, bukan dilewati dengan menonaktifkan pemeriksaan SSL.

### Security Monitoring
Security Assessment menggunakan pemeriksaan heuristik terhadap indikator tertentu pada HTML, termasuk pola JavaScript mencurigakan, indikasi defacement, iframe eksternal tersembunyi, SEO spam, dan redirect lintas domain.

Pemeriksaan ini bukan pengganti malware scanner menyeluruh atau penetration testing.

### Security Headers Audit
Pemeriksaan mencakup konfigurasi seperti:
- Content Security Policy (CSP)
- HTTP Strict Transport Security (HSTS)
- X-Content-Type-Options
- Frame Protection
- Referrer-Policy
- Permissions-Policy

Hasil audit disajikan dalam bentuk skor, status, temuan, dan rekomendasi.

## Struktur Repository

```text
SIMONTA/
│
├── backend/
│   ├── app/
│   │   ├── Console/
│   │   ├── Http/
│   │   ├── Jobs/
│   │   ├── Models/
│   │   ├── Notifications/
│   │   └── Services/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   ├── tests/
│   ├── .env.example
│   └── composer.json
│
├── frontend/
│   ├── public/
│   ├── src/
│   │   ├── components/
│   │   ├── contexts/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── routes/
│   │   ├── services/
│   │   └── styles/
│   ├── package.json
│   └── vite.config.js
│
├── .gitignore
└── README.md
```

## Persyaratan Sistem

Pastikan perangkat pengembangan memiliki:

- PHP 8.3 atau lebih baru.
- Composer.
- Node.js yang kompatibel dengan Vite 8.
- npm.
- MySQL 8.
- Git.

## Instalasi Backend

### 1. Clone repository

```bash
git clone https://github.com/Gevaldyar/SIMONTA.git
```

### 2. Masuk ke folder backend

```bash
cd SIMONTA/backend
```

### 3. Instal dependency

```bash
composer install
```

### 4. Siapkan environment

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 5. Buat database MySQL

```sql
CREATE DATABASE simonta_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

### 6. Konfigurasi `.env`

Sesuaikan koneksi database, URL aplikasi, dan konfigurasi autentikasi SPA.

Contoh:

```env
APP_NAME=SIMONTA
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simonta_db
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Sesuaikan username dan password MySQL dengan konfigurasi lokal.

### 7. Generate application key

```bash
php artisan key:generate
```

### 8. Jalankan migration

```bash
php artisan migrate
```

### 9. Isi katalog teknologi

```bash
php artisan db:seed --class=TechnologyCatalogSeeder
```

### 10. Buat akun administrator

```bash
php artisan simonta:make-admin
```

Ikuti instruksi untuk mengisi nama, email, dan password administrator. Password minimal 12 karakter.

### 11. Jalankan backend

```bash
php artisan serve
```

Backend tersedia di:

```text
http://localhost:8000
```

## Instalasi Frontend

Buka terminal baru.

### 1. Masuk ke folder frontend

```bash
cd SIMONTA/frontend
```

### 2. Instal dependency

```bash
npm ci
```

### 3. Siapkan environment frontend

Buat file `.env.local`:

```env
VITE_API_BASE_URL=http://localhost:8000
```

### 4. Jalankan frontend

```bash
npm run dev
```

Frontend tersedia di:

```text
http://localhost:5173
```

## Menjalankan Queue dan Scheduler

Untuk pengembangan lokal, jalankan backend, frontend, queue worker, dan scheduler menggunakan terminal terpisah.

Queue worker:

```bash
php artisan queue:work database -v
```

Scheduler:

```bash
php artisan schedule:work
```

Pastikan queue worker dan scheduler tetap aktif ketika monitoring otomatis sedang diuji.

## Pengujian

Menjalankan seluruh automated test backend:

```bash
php artisan test
```

Menjalankan production build frontend:

```bash
npm run build
```

Hasil pengujian lokal terakhir pada 22 November 2026:
- Backend: 204 tests passed.
- Assertions: 898.
- Frontend production build: berhasil.
- JavaScript entry bundle: 226.24 kB setelah minifikasi.

Hasil tersebut merupakan catatan pengujian pada lingkungan pengembangan, bukan jaminan kondisi deployment produksi.

## Keamanan

- Jangan mengunggah file `.env` atau kredensial ke repository.
- Gunakan akun administrator dengan password yang kuat.
- Batasi target monitoring pada website publik yang telah diizinkan.
- Pertahankan validasi URL dan pembatasan akses ke alamat IP internal.
- Jangan menonaktifkan verifikasi TLS untuk mengatasi masalah sertifikat website.
- Gunakan HTTPS dan `APP_DEBUG=false` pada lingkungan produksi.
- Sesuaikan CORS dan Sanctum stateful domains dengan domain frontend produksi.

## Deployment

Deployment produksi memerlukan koordinasi dengan administrator infrastruktur Diskominfo.

Komponen yang perlu disiapkan:
- Server aplikasi dan database.
- HTTPS dan domain aplikasi.
- Konfigurasi environment produksi.
- Queue worker yang dikelola secara persisten.
- Scheduler yang berjalan secara berkala.
- Backup database dan pemantauan log.

Panduan deployment perlu disesuaikan dengan infrastruktur yang disediakan Diskominfo.

## Repository

GitHub: https://github.com/Gevaldyar/SIMONTA

---

**SIMONTA — Sistem Monitoring Tautan Website OPD**

Diskominfo Kota Tasikmalaya
