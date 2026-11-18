import { Link } from 'react-router'

function NotFoundPage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-slate-100 p-6">
      <h1 className="text-7xl font-bold text-blue-700">404</h1>

      <p className="mt-3 text-lg font-semibold text-slate-800">
        Halaman Tidak Ditemukan
      </p>

      <Link
        to="/"
        className="mt-6 rounded-lg bg-blue-700 px-5 py-3 text-white hover:bg-blue-800"
      >
        Kembali ke Beranda
      </Link>
    </main>
  )
}

export default NotFoundPage