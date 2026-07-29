/**
 * @file React hook managing session user state and sign-in/out actions.
 */

import { ApiError, fetchCurrentUser, login, logout, normalizeUserLocale, type User, type UserLocale } from '@ellr/api-client'
import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

type UseAuthOptions = {
  onUserLoaded?: (user: User | null) => void | Promise<void>
  bootstrapErrorFallback?: string
}

const BOOTSTRAP_RETRY_ATTEMPTS = 5
const BOOTSTRAP_RETRY_DELAY_MS = 600
const BOOTSTRAP_AUTO_RETRY_MS = 3000

/**
 * @param ms Delay duration in milliseconds.
 * @returns Promise resolved after the delay.
 */
function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms)
  })
}

/**
 * @param error Bootstrap failure from the API client.
 * @returns Whether another bootstrap attempt may succeed.
 */
function isRetryableBootstrapError(error: unknown): boolean {
  if (error instanceof ApiError) {
    return error.status >= 500 || error.status === 429
  }

  return error instanceof TypeError
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
  const localeRef = useRef(locale)
  const onUserLoadedRef = useRef(onUserLoaded)
  const bootstrapErrorFallbackRef = useRef(resolvedBootstrapFallback)
  localeRef.current = locale
  onUserLoadedRef.current = onUserLoaded
  bootstrapErrorFallbackRef.current = resolvedBootstrapFallback
  const [user, setUser] = useState<User | null>(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [apiUnavailable, setApiUnavailable] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [bootstrapError, setBootstrapError] = useState<string | null>(null)

  const bootstrap = useCallback(async () => {
    setAuthLoading(true)
    setBootstrapError(null)
    setApiUnavailable(false)

    try {
      for (let attempt = 0; attempt < BOOTSTRAP_RETRY_ATTEMPTS; attempt++) {
        try {
          const currentUser = await fetchCurrentUser()
          applyUserLocale(setLocale, currentUser)
          setUser(currentUser)
          await onUserLoadedRef.current?.(currentUser)

          return
        } catch (caught) {
          if (attempt < BOOTSTRAP_RETRY_ATTEMPTS - 1 && isRetryableBootstrapError(caught)) {
            await sleep(BOOTSTRAP_RETRY_DELAY_MS)
            continue
          }

          if (isRetryableBootstrapError(caught)) {
            setApiUnavailable(true)

            return
          }

          setBootstrapError(
            getApiErrorMessage(caught, bootstrapErrorFallbackRef.current, localeRef.current),
          )
        }
      }
    } finally {
      setAuthLoading(false)
    }
  }, [setLocale])

  useEffect(() => {
    void bootstrap()
  }, [bootstrap])

  useEffect(() => {
    if (!apiUnavailable || authLoading) {
      return undefined
    }

    const timer = window.setInterval(() => {
      void bootstrap()
    }, BOOTSTRAP_AUTO_RETRY_MS)

    return () => {
      window.clearInterval(timer)
    }
  }, [apiUnavailable, authLoading, bootstrap])

  const { run: handleLogin, pending: loggingIn } = useGuardedAction(async (event: React.FormEvent) => {
    event.preventDefault()
    setBootstrapError(null)
    setApiUnavailable(false)

    try {
      const loggedInUser = await login(email, password)
      applyUserLocale(setLocale, loggedInUser)
      setUser(loggedInUser)
      setPassword('')
      await onUserLoadedRef.current?.(loggedInUser)
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
    apiUnavailable,
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
