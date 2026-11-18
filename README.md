# SIMONTA
### Sistem Monitoring Tautan Website OPD

**Diskominfo Kota Tasikmalaya**

SIMONTA (Sistem Monitoring Tautan Website OPD) merupakan aplikasi berbasis web yang dirancang untuk membantu administrator Diskominfo Kota Tasikmalaya dalam memantau ketersediaan website Organisasi Perangkat Daerah (OPD) dan mengidentifikasi teknologi yang digunakan oleh website tersebut.

Proyek ini berfokus pada pengembangan fitur identifikasi teknologi website serta monitoring otomatis dengan interval pemeriksaan setiap satu jam.

**Status Proyek:** Dalam Pengembangan

---

## Tujuan Proyek

1. Mempermudah pemantauan website OPD secara terpusat.
2. Memeriksa ketersediaan dan respons website secara otomatis.
3. Mengidentifikasi teknologi yang digunakan oleh website OPD.
4. Menyimpan riwayat hasil pemeriksaan dan identifikasi teknologi.
5. Menyediakan informasi monitoring melalui dashboard administrator.

## Teknologi yang Digunakan

### Backend
- PHP 8.5
- Laravel 13
- Laravel Sanctum
- MySQL 8

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

SIMONTA menggunakan arsitektur frontend dan backend terpisah.

```text
SIMONTA
│
├── Frontend
│   ├── React
│   ├── Vite
│   ├── Tailwind CSS
│   └── Axios
│
└── Backend
    ├── Laravel
    ├── REST API
    ├── Laravel Sanctum
    └── MySQL
```

React bertanggung jawab terhadap antarmuka administrator, sedangkan Laravel menangani proses bisnis, autentikasi, API, dan pengelolaan database.

## Rencana Fitur

| Fitur | Deskripsi | Status |
|---|---|---|
| Autentikasi Administrator | Login dan logout administrator | Direncanakan |
| Dashboard | Ringkasan kondisi website OPD | Direncanakan |
| Manajemen OPD | Pengelolaan data OPD | Direncanakan |
| Manajemen Website | Pengelolaan daftar website OPD | Direncanakan |
| Website Monitoring | Pemeriksaan ketersediaan website | Direncanakan |
| Automatic Monitoring | Pemeriksaan otomatis setiap satu jam | Direncanakan |
| Technology Detection | Identifikasi teknologi website | Direncanakan |
| Monitoring History | Riwayat pemeriksaan website | Direncanakan |
| Activity Logs | Pencatatan aktivitas administrator | Direncanakan |

*Status fitur akan diperbarui seiring perkembangan implementasi.*

## Struktur Repository

```text
simonta/
│
├── backend/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── tests/
│   └── ...
│
├── frontend/
│   ├── public/
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── contexts/
│   │   ├── hooks/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── routes/
│   │   ├── services/
│   │   └── utils/
│   ├── package.json
│   └── ...
│
├── .gitignore
└── README.md
```

## Persyaratan Pengembangan

Pastikan perangkat memiliki komponen berikut:

- PHP 8.3 atau lebih baru
- Composer
- Node.js yang kompatibel dengan Vite 8
- npm
- MySQL 8
- Git

Versi yang digunakan selama pengembangan awal tercatat pada dependency project masing-masing.

## Instalasi dan Menjalankan Backend

### 1. Clone Repository

```bash
git clone https://github.com/Gevaldyar/SIMONTA.git
```

### 2. Masuk ke direktori backend

```bash
cd simonta/backend
```

### 3. Instal dependency

```bash
composer install
```

### 4. Siapkan file environment

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

### 5. Konfigurasi database

Buat database MySQL dengan nama:

```sql
CREATE DATABASE simonta_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```

Sesuaikan konfigurasi pada file `.env`:

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
SESSION_DOMAIN=null

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Sesuaikan username dan password MySQL dengan environment lokal.

### 6. Generate application key

```bash
php artisan key:generate
```

### 7. Jalankan migration

```bash
php artisan migrate
```

### 8. Jalankan backend

```bash
php artisan serve
```

Backend tersedia di:

```text
http://localhost:8000
```

## Instalasi dan Menjalankan Frontend

Buka terminal baru.

### 1. Masuk ke direktori frontend

```bash
cd simonta/frontend
```

### 2. Instal dependency

```bash
npm ci
```

### 3. Siapkan environment frontend

Buat file `.env.local` dan isi:

```env
VITE_API_BASE_URL=http://localhost:8000
```

### 4. Jalankan development server

```bash
npm run dev
```

Frontend tersedia di:

```text
http://localhost:5173
```

### 5. Build untuk production

```bash
npm run build
```

## API dan Autentikasi

Backend menggunakan Laravel REST API dan Laravel Sanctum untuk menyiapkan autentikasi administrator.

Komunikasi frontend dan backend menggunakan Axios dengan dukungan session cookie dan CSRF untuk autentikasi SPA.

## Monitoring dan Identifikasi Teknologi

Rancangan sistem menggunakan pemeriksaan website publik secara otomatis dengan interval satu jam.

Metode identifikasi teknologi direncanakan memanfaatkan informasi HTTP response headers, HTML source, serta technology fingerprinting.

Hasil identifikasi akan disimpan sebagai riwayat pemeriksaan untuk mendukung evaluasi perubahan teknologi website.

Pemeriksaan hanya dilakukan terhadap website publik yang telah didaftarkan dan diizinkan dalam ruang lingkup monitoring.

## Keamanan

- File environment dan kredensial tidak boleh diunggah ke repository.
- Autentikasi administrator menggunakan mekanisme yang disediakan Laravel Sanctum.
- Pemeriksaan website dibatasi pada target yang telah didaftarkan.
- Request monitoring perlu menerapkan validasi URL dan pembatasan akses ke alamat internal.
- Konfigurasi production harus disesuaikan dengan lingkungan server Diskominfo.

## Repository

GitHub: https://github.com/Gevaldyar/SIMONTA

---

**SIMONTA — Sistem Monitoring Tautan Website OPD**

Diskominfo Kota Tasikmalaya

Status pengembangan dan dokumentasi fitur akan diperbarui sesuai implementasi proyek.