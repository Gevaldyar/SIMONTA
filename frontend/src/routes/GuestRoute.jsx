
import { Navigate, Outlet } from 'react-router'
import { useAuth } from '../contexts/AuthContext'

function GuestRoute() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-100">
        <p className="text-slate-600">Memuat SIMONTA...</p>
      </div>
    )
  }

  if (user) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}

export default GuestRoute
