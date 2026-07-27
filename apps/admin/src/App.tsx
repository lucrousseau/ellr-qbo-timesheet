/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
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
} from '@ellr/api-client'
import { Alert, AppShell, LoadingScreen, LoginForm, useAuth, cardClass, inputClass, primaryButtonClass, secondaryButtonClass } from '@ellr/ui'

/**
 * Admin app: QuickBooks OAuth and QBO employee mapping.
 * @returns Loading screen, sign-in, or admin dashboard depending on session.
 */
function App() {
  const [status, setStatus] = useState<QuickBooksStatus | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [connecting, setConnecting] = useState(false)
  const [qboEmployeeRef, setQboEmployeeRef] = useState('')
  const [qboEmployeeName, setQboEmployeeName] = useState('')
  const [savingEmployee, setSavingEmployee] = useState(false)

  const loadQuickBooksStatus = useCallback(async (currentUser: { qbo_employee_ref?: string | null; qbo_employee_name?: string | null } | null) => {
    if (!currentUser) {
      setStatus(null)
      return
    }

    setQboEmployeeRef(currentUser.qbo_employee_ref ?? '')
    setQboEmployeeName(currentUser.qbo_employee_name ?? '')
    setStatus(await fetchQuickBooksStatus())
  }, [])

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

  useEffect(() => {
    const { result, reason } = parseQuickBooksOAuthCallback(window.location.search)

    if (result === 'connected') {
      setNotice('QuickBooks connected successfully.')
      window.history.replaceState({}, '', window.location.pathname)
    }

    if (result === 'error') {
      setError(quickBooksOAuthErrorMessage(reason))
      window.history.replaceState({}, '', window.location.pathname)
    }
  }, [])

  const onLogout = async () => {
    setError(null)
    await handleLogout()
    setStatus(null)
  }

  const connectQuickBooksFlow = async () => {
    setConnecting(true)
    setError(null)

    try {
      const response = await connectQuickBooks()
      window.location.href = response.authorization_url
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Unable to start QuickBooks connection.'))
      setConnecting(false)
    }
  }

  const saveQboEmployee = async (event: React.FormEvent) => {
    event.preventDefault()
    setSavingEmployee(true)
    setError(null)

    try {
      const updatedUser = await updateQboEmployee(qboEmployeeRef, qboEmployeeName || undefined)
      setUser(updatedUser)
      setNotice('QuickBooks employee saved.')
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Unable to save the QuickBooks employee.'))
    } finally {
      setSavingEmployee(false)
    }
  }

  const disconnectQuickBooksFlow = async () => {
    try {
      await disconnectQuickBooks()
      setStatus({ connected: false })
      setNotice('QuickBooks disconnected.')
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Unable to disconnect QuickBooks.'))
    }
  }

  if (authLoading) {
    return <LoadingScreen />
  }

  if (!user) {
    return (
      <LoginForm
        title="Ellr QBO Timesheet"
        subtitle="Administration"
        email={email}
        password={password}
        onEmailChange={setEmail}
        onPasswordChange={setPassword}
        onSubmit={async (event) => {
          setError(null)
          await handleLogin(event)
        }}
        error={bootstrapError ?? error}
      />
    )
  }

  return (
    <AppShell
      title="Ellr QBO Timesheet"
      subtitle="Administration"
      userEmail={user.email}
      onLogout={onLogout}
    >
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">QuickBooks Online connection</h2>
        <p className="mt-2 text-sm text-slate-600">Signed in as {user.email}</p>

        <form className="mt-6 space-y-4 rounded-lg border border-slate-200 p-4" onSubmit={saveQboEmployee}>
          <h3 className="text-sm font-medium text-slate-900">QuickBooks employee</h3>
          <p className="text-sm text-slate-600">
            Link this account to a QBO employee for the timesheet app.
          </p>
          <label className="block text-sm font-medium text-slate-700">
            QBO employee ID
            <input
              required
              className={inputClass}
              value={qboEmployeeRef}
              onChange={(event) => setQboEmployeeRef(event.target.value)}
            />
          </label>
          <label className="block text-sm font-medium text-slate-700">
            Employee name (optional)
            <input
              className={inputClass}
              value={qboEmployeeName}
              onChange={(event) => setQboEmployeeName(event.target.value)}
            />
          </label>
          <button
            type="submit"
            disabled={savingEmployee}
            className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
          >
            {savingEmployee ? 'Saving...' : 'Save employee'}
          </button>
        </form>

        {notice && (
          <div className="mt-4">
            <Alert variant="success">{notice}</Alert>
          </div>
        )}

        {(error || bootstrapError) && (
          <div className="mt-4">
            <Alert variant="error">{error ?? bootstrapError}</Alert>
          </div>
        )}

        {status && (
          <p className="mt-4 text-sm text-slate-600">
            Status:{' '}
            <span className="font-medium text-slate-900">
              {status.connected ? `Connected (realm ${status.realm_id})` : 'Not connected'}
            </span>
          </p>
        )}

        <div className="mt-6 flex gap-3">
          <button
            type="button"
            onClick={connectQuickBooksFlow}
            disabled={connecting}
            className={primaryButtonClass}
          >
            {connecting ? 'Redirecting...' : 'Connect QuickBooks'}
          </button>
          {status?.connected && (
            <button type="button" onClick={disconnectQuickBooksFlow} className={`${secondaryButtonClass} px-4 py-2.5`}>
              Disconnect QuickBooks
            </button>
          )}
        </div>
      </section>
    </AppShell>
  )
}

export default App
