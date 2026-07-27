import { useCallback, useEffect, useState } from 'react'
import { fetchCurrentUser, getApiErrorMessage, login, logout, type User } from '@ellr/api-client'

type UseAuthOptions = {
  onUserLoaded?: (user: User | null) => void | Promise<void>
  bootstrapErrorFallback?: string
}

export function useAuth(options: UseAuthOptions = {}) {
  const { onUserLoaded, bootstrapErrorFallback = 'Impossible de joindre l\'API Laravel.' } = options
  const [user, setUser] = useState<User | null>(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [bootstrapError, setBootstrapError] = useState<string | null>(null)

  const bootstrap = useCallback(async () => {
    setAuthLoading(true)
    setBootstrapError(null)

    try {
      const currentUser = await fetchCurrentUser()
      setUser(currentUser)
      await onUserLoaded?.(currentUser)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, bootstrapErrorFallback))
    } finally {
      setAuthLoading(false)
    }
  }, [bootstrapErrorFallback, onUserLoaded])

  useEffect(() => {
    void bootstrap()
  }, [bootstrap])

  const handleLogin = async (event: React.FormEvent) => {
    event.preventDefault()
    setBootstrapError(null)

    try {
      const loggedInUser = await login(email, password)
      setUser(loggedInUser)
      setPassword('')
      await onUserLoaded?.(loggedInUser)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, 'Connexion impossible.'))
    }
  }

  const handleLogout = async () => {
    try {
      await logout()
      setUser(null)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, 'Déconnexion impossible.'))
    }
  }

  return {
    user,
    setUser,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    setBootstrapError,
    handleLogin,
    handleLogout,
    bootstrap,
  }
}
