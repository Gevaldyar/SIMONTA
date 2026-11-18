
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react'

import api from '../services/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // Check whether the administrator has an active session.
  const refreshUser = useCallback(async () => {
    try {
      const response = await api.get('/api/user')
      setUser(response.data)
    } catch (error) {
      setUser(null)

      if (!error.response) {
        console.error('Tidak dapat menghubungi server SIMONTA.', error)
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refreshUser()
  }, [refreshUser])

  // Login administrator using Sanctum SPA authentication.
  const login = useCallback(async (credentials) => {
    await api.get('/sanctum/csrf-cookie')

    const response = await api.post('/login', credentials)

    setUser(response.data.user)

    return response.data
  }, [])

  // Logout administrator.
  const logout = useCallback(async () => {
    await api.post('/logout')
    setUser(null)
  }, [])

  const value = useMemo(() => ({
    user,
    loading,
    login,
    logout,
    refreshUser,
  }), [user, loading, login, logout, refreshUser])

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth harus digunakan di dalam AuthProvider.')
  }

  return context
}
