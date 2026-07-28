/**
 * @file Admin QuickBooks OAuth, connection status, and employee mapping state.
 */

import { useCallback, useEffect, useState } from 'react'
import {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchQuickBooksStatus,
  getApiErrorMessage,
  normalizeUserLocale,
  parseQuickBooksOAuthCallback,
  type QuickBooksStatus,
  type User,
} from '@ellr/api-client'
import { useAuth, useFlashMessage, useLocale, usePasswordRecovery, useUserLocalePreferences } from '@ellr/ui'

/**
 * Maps a QuickBooks OAuth callback reason to a localized admin message key.
 * @param reason OAuth error reason from the callback query string.
 * @returns Message catalog key under `admin.*`.
 */
function quickBooksOAuthMessageKey(
  reason?: string | null,
):
  | 'admin.oauthDenied'
  | 'admin.oauthMissingParams'
  | 'admin.oauthInsufficientPermissions'
  | 'admin.oauthRealmConflict'
  | 'admin.oauthFailed' {
  if (reason === 'oauth') {
    return 'admin.oauthDenied'
  }

  if (reason === 'missing_params') {
    return 'admin.oauthMissingParams'
  }

  if (reason === 'insufficient_permissions') {
    return 'admin.oauthInsufficientPermissions'
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
  const [disconnecting, setDisconnecting] = useState(false)

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
      window.history.replaceState({}, '', window.location.pathname)
    }

    if (result === 'error') {
      showError(t(quickBooksOAuthMessageKey(reason)))
      window.history.replaceState({}, '', window.location.pathname)
    }
  }, [showError, showSuccess, t])

  const onLogin = async (event: React.FormEvent) => {
    clearMessage()
    await handleLogin(event)
  }

  const onLogout = async () => {
    clearMessage()
    await handleLogout()
    setStatus(null)
  }

  const connectQuickBooksFlow = async () => {
    setConnecting(true)

    try {
      const response = await connectQuickBooks()
      window.location.href = response.authorization_url
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('admin.connectFailed'), locale))
      setConnecting(false)
    }
  }

  const disconnectQuickBooksFlow = async () => {
    setDisconnecting(true)

    try {
      await disconnectQuickBooks()
      setStatus(await fetchQuickBooksStatus())
      showSuccess(t('admin.disconnectedSuccess'))
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('admin.disconnectFailed'), locale))
    } finally {
      setDisconnecting(false)
    }
  }

  return {
    user,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    onLogin,
    onLogout,
    message,
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
