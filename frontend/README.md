
# SIMONTA Frontend

Frontend SIMONTA dibangun menggunakan React 19, Vite 8, dan Tailwind CSS 4.

Aplikasi ini menyediakan antarmuka administrator untuk mengelola OPD, website, monitoring, identifikasi teknologi, pemeriksaan domain dan SSL, keamanan website, notifikasi, laporan, serta pengaturan akun.

## Teknologi

- React 19
- Vite 8
- Tailwind CSS 4
- Axios
- React Router

## Persyaratan

- Node.js yang kompatibel dengan Vite 8.
- npm.

## Instalasi

Jalankan dari direktori `frontend`.

### 1. Instal dependency

```bash
npm ci
```

### 2. Siapkan environment

Buat file `.env.local`:

```env
VITE_API_BASE_URL=http://localhost:8000
```

Variabel ini menentukan alamat REST API backend.

### 3. Jalankan development server

```bash
npm run dev
```

Frontend tersedia di:

```text
http://localhost:5173
```

## Build Production

Jalankan:

```bash
npm run build
```

File hasil build dibuat di direktori `dist/`.

Untuk melihat hasil build secara lokal:

```bash
npm run preview
```

## Fitur Frontend

- Authentication dan protected routes.
- Dashboard administrator.
- OPD Management.
- Website Registry.
- Monitoring Center.
- Technology Detection.
- Technology Changes.
- Inspection History.
- Domain & SSL Monitoring.
- Security Monitoring.
- Security Headers Audit.
- Notification History.
- Reports.
- Settings.

## Optimasi

Halaman aplikasi menggunakan React Lazy Loading dan route-based code splitting agar kode halaman dimuat sesuai kebutuhan navigasi.

## Struktur Folder

```text
frontend/
├── public/
├── src/
│   ├── components/
│   │   ├── common/
│   │   └── layout/
│   ├── contexts/
│   ├── layouts/
│   ├── pages/
│   ├── routes/
│   ├── services/
│   └── styles/
├── index.html
├── package.json
├── package-lock.json
└── vite.config.js
```

## Integrasi Backend

Komunikasi dengan backend menggunakan Axios dan Laravel Sanctum SPA authentication.

Konfigurasi lokal backend dan Sanctum harus mengizinkan origin frontend:

```text
http://localhost:5173
```

Pada deployment produksi, sesuaikan API URL, CORS, dan Sanctum stateful domains dengan domain aplikasi yang digunakan.
