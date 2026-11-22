
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'

import { AuthProvider } from './contexts/AuthContext'

import './index.css'
import App from './App'
import './styles/simonta-redesign.css'
import './styles/simonta-dark-mode.css'
import './styles/simonta-registry-redesign.css'
import './styles/simonta-monitoring-redesign.css'
import './styles/simonta-technology-redesign.css'
import './styles/simonta-navigation-shell.css'
import './styles/simonta-notification-badge-fix.css'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
