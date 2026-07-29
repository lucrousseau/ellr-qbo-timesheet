/**
 * @file Admin QuickBooks OAuth, connection status, and employee mapping state.
 */

import { useCallback, useEffect, useRef, useState } from 'react'
import {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchQuickBooksStatus,
  normalizeUserLocale,
  parseQuickBooksOAuthCallback,
  type QuickBooksStatus,
  type User,
} from '@ellr/api-client'
import { getApiErrorMessage, useAuth, useFlashMessage, useGuardedAction, useLocale, usePasswordRecovery, useUserLocalePreferences } from '@ellr/ui'

/**
 * Maps a QuickBooks OAuth callback reason to a localized message catalog key.
 * @param reason OAuth error reason from the callback query string.
 * @returns Message catalog key for `t()`.
 */
function quickBooksOAuthMessageKey(
  reason?: string | null,
):
  | 'admin.oauthDenied'
  | 'admin.oauthMissingParams'
  | 'api.errors.qbo_insufficient_permissions'
  | 'admin.oauthRealmConflict'
  | 'admin.oauthFailed' {
  if (reason === 'oauth') {
    return 'admin.oauthDenied'
  }

  if (reason === 'missing_params') {
    return 'admin.oauthMissingParams'
  }

  if (reason === 'insufficient_permissions') {
    return 'api.errors.qbo_insufficient_permissions'
  }

  if (reason === 'realm_conflict') {
    return 'admin.oauthRealmConflict'
  }

  return 'admin.oauthFailed'
}

/**
 * QuickBooks admin screen: session auth, OAuth callback, connect/disconnect.
 * @returns Auth fields, flash messages, and QuickBooks dashboard handlers.
 */
export function useQuickBooksAdmin() {
  const { locale, t } = useLocale()
  const { message, showError, showSuccess, clearMessage } = useFlashMessage()
  const [status, setStatus] = useState<QuickBooksStatus | null>(null)
  const [connecting, setConnecting] = useState(false)
  const [focusIntegrationsTab, setFocusIntegrationsTab] = useState(false)
  const connectInFlightRef = useRef(false)

  const loadQuickBooksStatus = useCallback(async (currentUser: User | null) => {
    if (!currentUser) {
      setStatus(null)
      return
    }

    if (currentUser.is_admin !== true) {
      setStatus(null)
      return
    }

    const messageLocale = normalizeUserLocale(currentUser.locale)

    try {
      setStatus(await fetchQuickBooksStatus())
    } catch (caught) {
      setStatus(null)
      showError(getApiErrorMessage(caught, t('admin.loadStatusFailed'), messageLocale))
    }
  }, [showError, t])

  const {
    user,
    setUser,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    handleLogin,
    handleLogout,
    loggingIn,
    loggingOut,
  } = useAuth({
    onUserLoaded: loadQuickBooksStatus,
  })

  const { preferenceLocale, setPreferenceLocale, savingLocale, saveLocale } = useUserLocalePreferences({
    user,
    setUser,
    onSaved: () => showSuccess(t('preferences.saved')),
    onError: (errorMessage) => showError(errorMessage),
  })

  const recovery = usePasswordRecovery({ client: 'admin' })

  useEffect(() => {
    const { result, reason } = parseQuickBooksOAuthCallback(window.location.search)

    if (result === 'connected') {
      showSuccess(t('admin.connectedSuccess'))
      setFocusIntegrationsTab(true)
      window.history.replaceState({}, '', window.location.pathname)
    }

    if (result === 'error') {
      showError(t(quickBooksOAuthMessageKey(reason)))
      setFocusIntegrationsTab(true)
      window.history.replaceState({}, '', window.location.pathname)
    }
  }, [showError, showSuccess, t])

  const clearFocusIntegrationsTab = useCallback(() => {
    setFocusIntegrationsTab(false)
  }, [])

  const onLogin = async (event: React.FormEvent) => {
    clearMessage()
    await handleLogin(event)
  }

  const onLogout = async () => {
    clearMessage()
    await handleLogout()
    setStatus(null)
  }

  const connectQuickBooksFlow = useCallback(async () => {
    if (connectInFlightRef.current) {
      return
    }

    connectInFlightRef.current = true
    setConnecting(true)

    try {
      const response = await connectQuickBooks()
      window.location.href = response.authorization_url
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('admin.connectFailed'), locale))
      connectInFlightRef.current = false
      setConnecting(false)
    }
  }, [locale, showError, t])

  const { run: disconnectQuickBooksFlow, pending: disconnecting } = useGuardedAction(async () => {
    try {
      await disconnectQuickBooks()
      setStatus(await fetchQuickBooksStatus())
      showSuccess(t('admin.disconnectedSuccess'))
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('admin.disconnectFailed'), locale))
    }
  })

  return {
    user,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    loggingIn,
    loggingOut,
    onLogin,
    onLogout,
    message,
    focusIntegrationsTab,
    clearFocusIntegrationsTab,
    status,
    connecting,
    disconnecting,
    preferenceLocale,
    setPreferenceLocale,
    savingLocale,
    connectQuickBooksFlow,
    disconnectQuickBooksFlow,
    saveLocale,
    showError,
    showSuccess,
    ...recovery,
  }
}
