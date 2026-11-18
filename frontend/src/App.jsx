
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
import NotFoundPage from './pages/errors/NotFoundPage'
import OpdManagementPage from './pages/opds/OpdManagementPage'
import WebsiteManagementPage from './pages/websites/WebsiteManagementPage'
import MonitoringCenterPage from './pages/monitoring/MonitoringCenterPage'
import TechnologyRegistryPage from './pages/technologies/TechnologyRegistryPage'
import ScanHistoryPage from './pages/history/ScanHistoryPage'
import SettingsPage from "./pages/settings/SettingsPage";
import ReportsPage from './pages/reports/ReportsPage'

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
  element={<OpdManagementPage />}
/>

          <Route
  path="websites"
  element={<WebsiteManagementPage />}
/>

          <Route
  path="monitoring"
  element={<MonitoringCenterPage />}
/>

          <Route
  path="technologies"
  element={<TechnologyRegistryPage />}
/>

          <Route
  path="history"
  element={<ScanHistoryPage />}
/>

          <Route
  path="reports"
  element={<ReportsPage />}
/>

          <Route
  path="settings"
  element={<SettingsPage />}
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
