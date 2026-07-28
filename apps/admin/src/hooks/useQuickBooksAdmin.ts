/**
 * @file Admin QuickBooks OAuth, connection status, and employee mapping state.
 */

import { useCallback, useEffect, useState } from 'react'
import {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchQuickBooksStatus,
  getApiErrorMessage,
  parseQuickBooksOAuthCallback,
  quickBooksOAuthErrorMessage,
  updateQboEmployee,
  type QuickBooksStatus,
  type User,
} from '@ellr/api-client'
import { useAuth, useFlashMessage, usePasswordRecovery } from '@ellr/ui'

/**
 * QuickBooks admin screen: session auth, OAuth callback, connect/disconnect, employee mapping.
 * @returns Auth fields, flash messages, and QuickBooks dashboard handlers.
 */
export function useQuickBooksAdmin() {
  const { message, showError, showSuccess, clearMessage } = useFlashMessage()
  const [status, setStatus] = useState<QuickBooksStatus | null>(null)
  const [connecting, setConnecting] = useState(false)
  const [qboEmployeeRef, setQboEmployeeRef] = useState('')
  const [qboEmployeeName, setQboEmployeeName] = useState('')
  const [savingEmployee, setSavingEmployee] = useState(false)
  const [disconnecting, setDisconnecting] = useState(false)

  const loadQuickBooksStatus = useCallback(async (currentUser: User | null) => {
    if (!currentUser) {
      setStatus(null)
      return
    }

    setQboEmployeeRef(currentUser.qbo_employee_ref ?? '')
    setQboEmployeeName(currentUser.qbo_employee_name ?? '')

    try {
      setStatus(await fetchQuickBooksStatus())
    } catch (caught) {
      setStatus(null)
      showError(getApiErrorMessage(caught, 'Unable to load QuickBooks status.'))
    }
  }, [showError])

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
    bootstrapErrorFallback: 'Unable to load the application.',
  })

  const recovery = usePasswordRecovery({ client: 'admin' })

  useEffect(() => {
    const { result, reason } = parseQuickBooksOAuthCallback(window.location.search)

    if (result === 'connected') {
      showSuccess('QuickBooks connected successfully.')
      window.history.replaceState({}, '', window.location.pathname)
    }

    if (result === 'error') {
      showError(quickBooksOAuthErrorMessage(reason))
      window.history.replaceState({}, '', window.location.pathname)
    }
  }, [showError, showSuccess])

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
      showError(getApiErrorMessage(caught, 'Unable to start QuickBooks connection.'))
      setConnecting(false)
    }
  }

  const saveQboEmployee = async (event: React.FormEvent) => {
    event.preventDefault()
    setSavingEmployee(true)

    try {
      const updatedUser = await updateQboEmployee(qboEmployeeRef, qboEmployeeName || undefined)
      setUser(updatedUser)
      showSuccess('QuickBooks employee saved.')
    } catch (caught) {
      showError(getApiErrorMessage(caught, 'Unable to save the QuickBooks employee.'))
    } finally {
      setSavingEmployee(false)
    }
  }

  const disconnectQuickBooksFlow = async () => {
    setDisconnecting(true)

    try {
      await disconnectQuickBooks()
      setStatus(await fetchQuickBooksStatus())
      showSuccess('QuickBooks disconnected.')
    } catch (caught) {
      showError(getApiErrorMessage(caught, 'Unable to disconnect QuickBooks.'))
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
    qboEmployeeRef,
    setQboEmployeeRef,
    qboEmployeeName,
    setQboEmployeeName,
    savingEmployee,
    connectQuickBooksFlow,
    disconnectQuickBooksFlow,
    saveQboEmployee,
    ...recovery,
  }
}
