
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useAuth } from '../../contexts/AuthContext'

function DashboardPage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [error, setError] = useState('')

  const handleLogout = async () => {
    setIsLoggingOut(true)
    setError('')

    try {
      await logout()
      navigate('/login', { replace: true })
    } catch {
      setError('Logout gagal. Silakan coba kembali.')
    } finally {
      setIsLoggingOut(false)
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 p-6 sm:p-10">
      <div className="mx-auto max-w-5xl">

        <header className="flex flex-col justify-between gap-4 rounded-xl bg-white p-6 shadow-sm sm:flex-row sm:items-center">
          <div>
            <p className="text-sm font-semibold text-blue-700">
              SIMONTA
            </p>

            <h1 className="mt-1 text-2xl font-bold text-slate-900">
              Dashboard Administrator
            </h1>

            <p className="mt-1 text-sm text-slate-500">
              Diskominfo Kota Tasikmalaya
            </p>
          </div>

          <button
            type="button"
            onClick={handleLogout}
            disabled={isLoggingOut}
            className="rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
          >
            {isLoggingOut ? 'Memproses...' : 'Logout'}
          </button>
        </header>

        <section className="mt-6 rounded-xl bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">
            Selamat datang, {user?.name}
          </h2>

          <p className="mt-2 text-sm text-slate-600">
            Kamu berhasil masuk ke sistem SIMONTA.
          </p>

          <div className="mt-5 rounded-lg bg-blue-50 p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-blue-700">
              Akun administrator
            </p>

            <p className="mt-1 font-medium text-slate-900">
              {user?.email}
            </p>
          </div>
        </section>

        {error && (
          <p role="alert" className="mt-4 text-sm text-red-600">
            {error}
          </p>
        )}

      </div>
    </main>
  )
}

export default DashboardPage
