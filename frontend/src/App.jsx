
import {
  Navigate,
  Route,
  Routes,
} from 'react-router'

import GuestRoute from './routes/GuestRoute'
import ProtectedRoute from './routes/ProtectedRoute'

import AuthLayout from './layouts/AuthLayout'

import LoginPage from './pages/auth/LoginPage'
import DashboardPage from './pages/dashboard/DashboardPage'
import NotFoundPage from './pages/errors/NotFoundPage'

function App() {
  return (
    <Routes>

      {/* Guest routes */}
      <Route element={<GuestRoute />}>
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<LoginPage />} />
        </Route>
      </Route>

      {/* Protected administrator routes */}
      <Route element={<ProtectedRoute />}>
        <Route path="/dashboard" element={<DashboardPage />} />
      </Route>

      {/* Default route */}
      <Route
        path="/"
        element={<Navigate to="/dashboard" replace />}
      />

      {/* Unknown route */}
      <Route path="*" element={<NotFoundPage />} />

    </Routes>
  )
}

export default App
