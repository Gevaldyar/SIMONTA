
import { lazy, Suspense } from 'react'

import {
  Navigate,
  Route,
  Routes,
} from 'react-router'

import GuestRoute from './routes/GuestRoute'
import ProtectedRoute from './routes/ProtectedRoute'

// Layout tetap dipisahkan agar dapat dimuat sesuai area aplikasi.
const AuthLayout = lazy(() => import('./layouts/AuthLayout'))
const DashboardLayout = lazy(() => import('./layouts/DashboardLayout'))

// Authentication
const LoginPage = lazy(() => import('./pages/auth/LoginPage'))

// Dashboard
const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage'))

// OPD and Website Management
const OpdManagementPage = lazy(() => import('./pages/opds/OpdManagementPage'))
const WebsiteManagementPage = lazy(() => import('./pages/websites/WebsiteManagementPage'))

// Monitoring
const MonitoringCenterPage = lazy(() => import('./pages/monitoring/MonitoringCenterPage'))
const DomainSslMonitoringPage = lazy(() => import('./pages/domain-ssl/DomainSslMonitoringPage'))

// Technology
const TechnologyRegistryPage = lazy(() => import('./pages/technologies/TechnologyRegistryPage'))
const TechnologyChangesPage = lazy(() => import('./pages/technology-changes/TechnologyChangesPage'))

// Security
const SecurityMonitoringPage = lazy(() => import('./pages/security/SecurityMonitoringPage'))
const SecurityHeadersAuditPage = lazy(() => import('./pages/security-headers/SecurityHeadersAuditPage'))

// History, Reports, Notifications, Settings
const ScanHistoryPage = lazy(() => import('./pages/history/ScanHistoryPage'))
const NotificationHistoryPage = lazy(() => import('./pages/notifications/NotificationHistoryPage'))
const ReportsPage = lazy(() => import('./pages/reports/ReportsPage'))
const SettingsPage = lazy(() => import('./pages/settings/SettingsPage'))

// Error pages
const NotFoundPage = lazy(() => import('./pages/errors/NotFoundPage'))

function App() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center text-sm text-slate-500">
          Memuat halaman...
        </div>
      }
    >
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
              path="domain-ssl"
              element={<DomainSslMonitoringPage />}
            />

            <Route
              path="technologies"
              element={<TechnologyRegistryPage />}
            />

            <Route
              path="technology-changes"
              element={<TechnologyChangesPage />}
            />

            <Route
              path="history"
              element={<ScanHistoryPage />}
            />

            <Route
              path="notifications"
              element={<NotificationHistoryPage />}
            />

            <Route
              path="reports"
              element={<ReportsPage />}
            />

            <Route
              path="settings"
              element={<SettingsPage />}
            />

            <Route
              path="security"
              element={<SecurityMonitoringPage />}
            />

            <Route
              path="security-headers"
              element={<SecurityHeadersAuditPage />}
            />

          </Route>
        </Route>

        {/* Not found */}
        <Route
          path="*"
          element={<NotFoundPage />}
        />
      </Routes>
    </Suspense>
  )
}

export default App
