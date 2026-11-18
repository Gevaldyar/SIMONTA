
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useAuth } from '../../contexts/AuthContext'

function LoginPage() {
  const navigate = useNavigate()
  const { login } = useAuth()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()

    setError('')
    setIsSubmitting(true)

    try {
      await login({
        email: email.trim(),
        password,
      })

      navigate('/dashboard', { replace: true })
    } catch (err) {
      const status = err.response?.status

      if (status === 422) {
        setError(
          err.response?.data?.message ||
          'Email atau password tidak sesuai.'
        )
      } else if (status === 429) {
        setError(
          'Terlalu banyak percobaan login. Silakan coba beberapa saat lagi.'
        )
      } else if (status === 419) {
        setError(
          'Sesi keamanan telah kedaluwarsa. Silakan muat ulang halaman.'
        )
      } else {
        setError(
          'Tidak dapat menghubungi server SIMONTA. Periksa koneksi backend.'
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <section className="rounded-2xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/60 sm:p-9">

      <div className="mb-7">
        <h2 className="text-2xl font-bold text-slate-900">
          Selamat Datang
        </h2>

        <p className="mt-2 text-sm text-slate-500">
          Silakan masuk menggunakan akun administrator SIMONTA.
        </p>
      </div>

      {error && (
        <div
          role="alert"
          className="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
        >
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-5">

        <div>
          <label
            htmlFor="email"
            className="mb-2 block text-sm font-medium text-slate-700"
          >
            Email Administrator
          </label>

          <input
            id="email"
            type="email"
            autoComplete="username"
            required
            maxLength={255}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            placeholder="Masukkan email administrator"
            className="w-full rounded-lg border border-slate-300 px-4 py-3 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
          />
        </div>

        <div>
          <label
            htmlFor="password"
            className="mb-2 block text-sm font-medium text-slate-700"
          >
            Password
          </label>

          <div className="relative">
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              placeholder="Masukkan password"
              className="w-full rounded-lg border border-slate-300 px-4 py-3 pr-20 text-sm outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
            />

            <button
              type="button"
              onClick={() => setShowPassword((value) => !value)}
              className="absolute inset-y-0 right-3 text-xs font-medium text-blue-700 hover:text-blue-900"
            >
              {showPassword ? 'Sembunyikan' : 'Tampilkan'}
            </button>
          </div>
        </div>

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full rounded-lg bg-blue-700 px-4 py-3 font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? 'Memproses...' : 'Masuk ke SIMONTA'}
        </button>

      </form>
    </section>
  )
}

export default LoginPage
