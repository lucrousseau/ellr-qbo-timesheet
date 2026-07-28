/**
 * @file React hook managing session user state and sign-in/out actions.
 */

import { useCallback, useEffect, useState } from 'react'
import {
  fetchCurrentUser,
  login,
  logout,
  normalizeUserLocale,
  type User,
  type UserLocale,
} from '@ellr/api-client'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

type UseAuthOptions = {
  onUserLoaded?: (user: User | null) => void | Promise<void>
  bootstrapErrorFallback?: string
}

/**
 * Synchronizes the locale provider with the signed-in user (or English when signed out).
 * @param setLocale Locale setter from LocaleProvider.
 * @param user Authenticated user, or null when signed out.
 */
function applyUserLocale(
  setLocale: (locale: UserLocale) => void,
  user: User | null,
): UserLocale {
  const nextLocale = user ? normalizeUserLocale(user.locale) : 'en'
  setLocale(nextLocale)

  return nextLocale
}

/**
 * Manages Sanctum session bootstrap, sign-in, and sign-out.
 * @param options.onUserLoaded Callback after a successful load or sign-in.
 * @param options.bootstrapErrorFallback Message when the API is unreachable at startup.
 * @returns User state, login fields, and handlers.
 */
export function useAuth(options: UseAuthOptions = {}) {
  const { locale, setLocale, t } = useLocale()
  const { onUserLoaded, bootstrapErrorFallback } = options
  const resolvedBootstrapFallback = bootstrapErrorFallback ?? t('admin.bootstrapFailed')
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
      applyUserLocale(setLocale, currentUser)
      setUser(currentUser)
      await onUserLoaded?.(currentUser)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, resolvedBootstrapFallback, locale))
    } finally {
      setAuthLoading(false)
    }
  }, [locale, onUserLoaded, resolvedBootstrapFallback, setLocale])

  useEffect(() => {
    void bootstrap()
  }, [bootstrap])

  const { run: handleLogin, pending: loggingIn } = useGuardedAction(async (event: React.FormEvent) => {
    event.preventDefault()
    setBootstrapError(null)

    try {
      const loggedInUser = await login(email, password)
      applyUserLocale(setLocale, loggedInUser)
      setUser(loggedInUser)
      setPassword('')
      await onUserLoaded?.(loggedInUser)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, t('common.signInFailed'), locale))
    }
  })

  const { run: handleLogout, pending: loggingOut } = useGuardedAction(async () => {
    try {
      await logout()
      applyUserLocale(setLocale, null)
      setUser(null)
    } catch (caught) {
      setBootstrapError(getApiErrorMessage(caught, t('common.signOutFailed'), locale))
    }
  })

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
    loggingIn,
    loggingOut,
    bootstrap,
  }
}
