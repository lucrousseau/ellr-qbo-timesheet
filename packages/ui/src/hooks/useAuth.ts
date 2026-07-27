import { useCallback, useEffect, useState } from 'react'
import { fetchCurrentUser, getApiErrorMessage, login, logout, type User } from '@ellr/api-client'

type UseAuthOptions = {
  onUserLoaded?: (user: User | null) => void | Promise<void>
  bootstrapErrorFallback?: string
}

/**
 * Manages Sanctum session bootstrap, sign-in, and sign-out.
 * @param options.onUserLoaded Callback after a successful load or sign-in.
 * @param options.bootstrapErrorFallback Message when the API is unreachable at startup.
 * @returns User state, login fields, and handlers.
 */
export function useAuth(options: UseAuthOptions = {}) {
  const { onUserLoaded, bootstrapErrorFallback = 'Unable to reach the Laravel API.' } = options
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
      setBootstrapError(getApiErrorMessage(caught, 'Sign-in failed.'))
    }
  }

  const handleLogout = async () => {
    try {
      await logout()
      setUser(null)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, 'Sign-out failed.'))
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
