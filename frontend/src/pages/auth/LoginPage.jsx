
import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useAuth } from '../../contexts/AuthContext'

function EyeIcon({ visible }) {
  return visible ? (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M3 3l18 18" />
      <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
      <path d="M9.9 5.1A11.4 11.4 0 0 1 12 5c5 0 8.5 4 9.5 7-.4 1.3-1.3 2.6-2.5 3.7" />
      <path d="M6.2 6.2C3.9 7.5 2.7 9.7 2.5 12c1 3 4.5 7 9.5 7 1.1 0 2.1-.2 3-.5" />
    </svg>
  ) : (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" />
      <circle cx="12" cy="12" r="2.8" />
    </svg>
  )
}

function ArrowIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M5 12h14" />
      <path d="m13 6 6 6-6 6" />
    </svg>
  )
}

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
    // Siapkan penanda sebelum AuthContext memperbarui user.
    // Ini mencegah DashboardLayout terpasang terlalu awal.
    try {
      sessionStorage.setItem(
        'simonta-entry-transition',
        String(Date.now())
      )
    } catch {
      // Login tetap dapat berjalan tanpa sessionStorage.
    }

    await login({
      email: email.trim(),
      password,
    })

    navigate('/dashboard', { replace: true })
  } catch (err) {
    // Hapus penanda jika autentikasi gagal.
    try {
      sessionStorage.removeItem('simonta-entry-transition')
    } catch {
      // Abaikan jika sessionStorage tidak tersedia.
    }

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
    <section>
      {/* Section marker */}
      <div className="mb-7 flex items-center gap-3">
        <span className="font-serif text-sm italic text-[#963746]">
          01
        </span>

        <span className="h-px w-8 bg-[#B69A69]" />

        <span className="text-[9px] font-semibold tracking-[0.2em] text-[#817A70]">
          ADMINISTRATOR PORTAL
        </span>
      </div>

      {/* Heading */}
      <div className="mb-9">
        <h2 className="
          font-serif text-4xl leading-[1.08]
          tracking-[-0.035em] text-[#191A20]
          sm:text-5xl
        ">
          Welcome
          <br />
          <span className="italic text-[#963746]">
            back.
          </span>
        </h2>

        <p className="mt-5 max-w-[350px] text-xs leading-6 text-[#77736C] sm:text-sm">
          Masuk ke ruang kontrol SIMONTA untuk memantau
          infrastruktur digital dan website OPD.
        </p>
      </div>

      {/* Authentication error */}
      {error && (
        <div
          role="alert"
          className="
            mb-6 flex gap-3 border-l-[3px]
            border-[#963746] bg-[#963746]/[0.06]
            px-4 py-3.5 text-xs leading-5 text-[#843440]
          "
        >
          <svg
            className="mt-0.5 shrink-0"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
          >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v4" />
            <path d="M12 16h.01" />
          </svg>

          <span>{error}</span>
        </div>
      )}

      {/* Login form */}
      <form onSubmit={handleSubmit} className="space-y-6">

        {/* Email */}
        <div>
          <label
            htmlFor="email"
            className="
              mb-2.5 flex items-center justify-between
              text-[9px] font-bold tracking-[0.15em]
              text-[#665F56]
            "
          >
            <span>EMAIL ADDRESS</span>
            <span className="font-normal text-[#A49A89]">
              REQUIRED
            </span>
          </label>

          <input
            id="email"
            type="email"
            autoComplete="username"
            autoFocus
            required
            maxLength={255}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            placeholder="administrator@example.go.id"
            className="
              h-[54px] w-full border border-[#D0C4B2]
              bg-[#F7F4EC] px-4 text-sm text-[#191A20]
              outline-none transition duration-200
              placeholder:text-[#AAA194]
              hover:border-[#B69A69]
              focus:border-[#963746]
              focus:bg-white
              focus:shadow-[0_0_0_3px_rgba(150,55,70,0.07)]
            "
          />
        </div>

        {/* Password */}
        <div>
          <div className="mb-2.5 flex items-center justify-between">
            <label
              htmlFor="password"
              className="
                text-[9px] font-bold tracking-[0.15em]
                text-[#665F56]
              "
            >
              PASSWORD
            </label>

            <span className="text-[9px] font-normal tracking-wider text-[#A49A89]">
              SECURE CREDENTIAL
            </span>
          </div>

          <div className="relative">
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              placeholder="Enter your password"
              className="
                h-[54px] w-full border border-[#D0C4B2]
                bg-[#F7F4EC] px-4 pr-14 text-sm
                text-[#191A20] outline-none
                transition duration-200
                placeholder:text-[#AAA194]
                hover:border-[#B69A69]
                focus:border-[#963746]
                focus:bg-white
                focus:shadow-[0_0_0_3px_rgba(150,55,70,0.07)]
              "
            />

            <button
              type="button"
              onClick={() => setShowPassword((value) => !value)}
              aria-label={
                showPassword ? 'Sembunyikan password' : 'Tampilkan password'
              }
              aria-pressed={showPassword}
              className="
                absolute inset-y-0 right-0 grid w-12
                place-items-center text-[#817A70]
                transition hover:text-[#963746]
              "
            >
              <EyeIcon visible={showPassword} />
            </button>
          </div>
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={isSubmitting}
          className="
            group flex h-[56px] w-full items-center
            justify-between bg-[#252B37] px-5
            text-xs font-semibold tracking-[0.12em]
            text-[#EEE9DD] transition duration-200
            hover:bg-[#963746]
            disabled:cursor-not-allowed disabled:opacity-60
          "
        >
          <span>
            {isSubmitting ? 'AUTHENTICATING...' : 'SIGN IN TO SIMONTA'}
          </span>

          {isSubmitting ? (
            <span className="
              size-4 animate-spin rounded-full
              border-2 border-[#EEE9DD]/30
              border-t-[#EEE9DD]
            " />
          ) : (
            <span className="
              transition-transform duration-200
              group-hover:translate-x-1
            ">
              <ArrowIcon />
            </span>
          )}
        </button>
      </form>

      {/* Security note */}
      <div className="
        mt-8 flex items-start gap-3
        border-t border-[#D8CFBF] pt-5
      ">
        <svg
          className="mt-0.5 shrink-0 text-[#82918B]"
          width="17"
          height="17"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.6"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden="true"
        >
          <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
          <path d="m9 12 2 2 4-4" />
        </svg>

        <div>
          <p className="text-[9px] font-semibold tracking-[0.14em] text-[#55584F]">
            PROTECTED ADMINISTRATOR ACCESS
          </p>

          <p className="mt-1 text-[10px] leading-5 text-[#8A8378]">
            Sistem ini hanya dapat diakses oleh administrator
            yang memiliki otorisasi.
          </p>
        </div>
      </div>
    </section>
  )
}

export default LoginPage
