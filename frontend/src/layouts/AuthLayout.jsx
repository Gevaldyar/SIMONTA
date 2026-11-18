
import { Outlet } from 'react-router'

function AuthLayout() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 px-5 py-10">
      <div className="w-full max-w-md">

        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-700 text-2xl font-bold text-white shadow-lg">
            S
          </div>

          <h1 className="text-3xl font-bold tracking-tight text-slate-900">
            SIMONTA
          </h1>

          <p className="mt-2 text-sm text-slate-600">
            Sistem Monitoring Tautan Website OPD
          </p>

          <p className="mt-1 text-xs text-slate-500">
            Diskominfo Kota Tasikmalaya
          </p>
        </div>

        <Outlet />

        <p className="mt-8 text-center text-xs text-slate-500">
          SIMONTA &copy; {new Date().getFullYear()}
          {' '}Diskominfo Kota Tasikmalaya
        </p>

      </div>
    </main>
  )
}

export default AuthLayout
