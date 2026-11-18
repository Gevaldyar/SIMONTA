
import {
  Navigate,
  Route,
  Routes,
} from 'react-router'

import GuestRoute from './routes/GuestRoute'
import ProtectedRoute from './routes/ProtectedRoute'

import AuthLayout from './layouts/AuthLayout'
import DashboardLayout from './layouts/DashboardLayout'

import LoginPage from './pages/auth/LoginPage'
import DashboardPage from './pages/dashboard/DashboardPage'
import ModulePlaceholderPage from './pages/ModulePlaceholderPage'
import NotFoundPage from './pages/errors/NotFoundPage'

function App() {
  return (
    <Routes>

      {/* Public guest area */}
      <Route element={<GuestRoute />}>
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
        </Route>
      </Route>

      {/* Protected administrator area */}
      <Route element={<ProtectedRoute />}>
        <Route element={<DashboardLayout />}>

          <Route index element={<DashboardPage />} />

          <Route
            path="dashboard"
            element={<Navigate to="/" replace />}
          />

          <Route
            path="opds"
            element={
              <ModulePlaceholderPage
                section="02"
                title="OPD Registry"
                description="Pengelolaan dan pendataan organisasi perangkat daerah Kota Tasikmalaya."
              />
            }
          />

          <Route
            path="websites"
            element={
              <ModulePlaceholderPage
                section="03"
                title="Website Registry"
                description="Pusat pengelolaan alamat website publik yang akan dimasukkan ke dalam sistem monitoring."
              />
            }
          />

          <Route
            path="monitoring"
            element={
              <ModulePlaceholderPage
                section="04"
                title="Monitoring Center"
                description="Pemantauan ketersediaan dan waktu respons website OPD secara terjadwal."
              />
            }
          />

          <Route
            path="technologies"
            element={
              <ModulePlaceholderPage
                section="05"
                title="Technology Intelligence"
                description="Informasi teknologi website yang berhasil diidentifikasi melalui pemeriksaan."
              />
            }
          />

          <Route
            path="history"
            element={
              <ModulePlaceholderPage
                section="06"
                title="Inspection History"
                description="Arsip hasil monitoring dan identifikasi teknologi dari setiap siklus pemeriksaan."
              />
            }
          />

          <Route
            path="settings"
            element={
              <ModulePlaceholderPage
                section="07"
                title="System Configuration"
                description="Konfigurasi sistem, akun administrator, serta parameter monitoring SIMONTA."
              />
            }
          />

        </Route>
      </Route>

      <Route
        path="*"
        element={<NotFoundPage />}
      />

    </Routes>
  )
}

export default App
