
import { useState } from 'react'
import { Outlet, useNavigate } from 'react-router'

import Sidebar from '../components/layout/Sidebar'
import Topbar from '../components/layout/Topbar'
import { useAuth } from '../contexts/AuthContext'

function DashboardLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const [isLoggingOut, setIsLoggingOut] = useState(false)
  const [logoutError, setLogoutError] = useState('')

  const handleLogout = async () => {
    setLogoutError('')
    setIsLoggingOut(true)

    try {
      await logout()
      navigate('/login', { replace: true })
    } catch (error) {
      console.error('Logout error:', error)
      setLogoutError('Logout gagal. Periksa koneksi server dan coba kembali.')
    } finally {
      setIsLoggingOut(false)
    }
  }

  const closeSidebar = () => {
    setIsSidebarOpen(false)
  }

  return (
    <div className="min-h-screen bg-[#EEE9DD] text-[#191A20] md:flex">

      {isSidebarOpen && (
        <button
          type="button"
          aria-label="Tutup navigasi"
          onClick={closeSidebar}
          className="fixed inset-0 z-40 bg-[#191A20]/60 md:hidden"
        />
      )}

      <Sidebar
        isOpen={isSidebarOpen}
        onNavigate={closeSidebar}
      />

      <div className="min-h-screen min-w-0 flex-1">

        <Topbar
          user={user}
          onMenuClick={() => setIsSidebarOpen(true)}
          onLogout={handleLogout}
          isLoggingOut={isLoggingOut}
        />

        {logoutError && (
          <div
            role="alert"
            className="mx-5 mt-5 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700 sm:mx-8"
          >
            {logoutError}
          </div>
        )}

        <main className="mx-auto min-h-[calc(100vh-76px)] w-full max-w-[1600px] px-5 py-8 sm:px-8 lg:px-10">
          <Outlet />
        </main>

        <footer className="border-t border-[#D8CFBF] px-5 py-5 sm:px-8 lg:px-10">
          <div className="flex flex-col justify-between gap-2 text-[9px] tracking-[0.15em] text-[#77736C] sm:flex-row">
            <span>SIMONTA / DIGITAL OBSERVATORY</span>
            <span>DISKOMINFO KOTA TASIKMALAYA</span>
          </div>
        </footer>

      </div>
    </div>
  )
}

export default DashboardLayout
