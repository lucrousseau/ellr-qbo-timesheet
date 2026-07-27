import { useEffect, useState } from 'react'
import {
  apiFetch,
  fetchCurrentUser,
  getApiErrorMessage,
  login,
  logout,
  updateQboEmployee,
  type User,
} from '@ellr/api-client'

type QuickBooksStatus = {
  connected: boolean
  realm_id?: string
}

function App() {
  const [user, setUser] = useState<User | null>(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [status, setStatus] = useState<QuickBooksStatus | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [connecting, setConnecting] = useState(false)
  const [qboEmployeeRef, setQboEmployeeRef] = useState('')
  const [qboEmployeeName, setQboEmployeeName] = useState('')
  const [savingEmployee, setSavingEmployee] = useState(false)

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const quickbooksResult = params.get('quickbooks')

    if (quickbooksResult === 'connected') {
      setNotice('QuickBooks connecté avec succès.')
      window.history.replaceState({}, '', window.location.pathname)
    }

    if (quickbooksResult === 'error') {
      const reason = params.get('reason')
      const message = reason === 'oauth'
        ? 'Connexion QuickBooks refusée ou expirée.'
        : reason === 'missing_params'
          ? 'Paramètres OAuth manquants.'
          : 'Connexion QuickBooks impossible.'
      setError(message)
      window.history.replaceState({}, '', window.location.pathname)
    }

    fetchCurrentUser()
      .then((currentUser) => {
        setUser(currentUser)
        if (currentUser) {
          setQboEmployeeRef(currentUser.qbo_employee_ref ?? '')
          setQboEmployeeName(currentUser.qbo_employee_name ?? '')
          return apiFetch<QuickBooksStatus>('/quickbooks/status').then(setStatus)
        }
        return undefined
      })
      .catch((caught) => setError(getApiErrorMessage(caught, 'Impossible de charger l\'application.')))
      .finally(() => setAuthLoading(false))
  }, [])

  const handleLogin = async (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)

    try {
      const loggedInUser = await login(email, password)
      setUser(loggedInUser)
      setPassword('')
      setQboEmployeeRef(loggedInUser.qbo_employee_ref ?? '')
      setQboEmployeeName(loggedInUser.qbo_employee_name ?? '')
      const quickBooksStatus = await apiFetch<QuickBooksStatus>('/quickbooks/status')
      setStatus(quickBooksStatus)
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Connexion impossible.'))
    }
  }

  const handleLogout = async () => {
    try {
      await logout()
      setUser(null)
      setStatus(null)
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Déconnexion impossible.'))
    }
  }

  const connectQuickBooks = async () => {
    setConnecting(true)
    setError(null)

    try {
      const response = await apiFetch<{ authorization_url: string }>('/quickbooks/connect')
      window.location.href = response.authorization_url
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Impossible de démarrer la connexion QuickBooks.'))
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
      setNotice('Employé QuickBooks enregistré.')
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Impossible d\'enregistrer l\'employé QuickBooks.'))
    } finally {
      setSavingEmployee(false)
    }
  }

  const disconnectQuickBooks = async () => {
    try {
      await apiFetch('/quickbooks/disconnect', { method: 'POST' })
      setStatus({ connected: false })
      setNotice('QuickBooks déconnecté.')
    } catch (caught) {
      setError(getApiErrorMessage(caught, 'Déconnexion QuickBooks impossible.'))
    }
  }

  if (authLoading) {
    return (
      <main className="mx-auto max-w-3xl px-6 py-10">
        <p className="text-slate-600">Chargement...</p>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-3xl px-6 py-10">
      <header className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900">
            Ellr QBO Timesheet
          </h1>
          <p className="mt-2 text-slate-600">Interface d'administration</p>
        </div>
        {user && (
          <button
            type="button"
            onClick={handleLogout}
            className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          >
            Déconnexion
          </button>
        )}
      </header>

      {!user ? (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-xl font-medium text-slate-900">Connexion</h2>

          {error && (
            <p className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
          )}

          <form className="mt-4 space-y-4" onSubmit={handleLogin}>
            <label className="block text-sm font-medium text-slate-700">
              Courriel
              <input
                type="email"
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium text-slate-700">
              Mot de passe
              <input
                type="password"
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </label>
            <button
              type="submit"
              className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
            >
              Se connecter
            </button>
          </form>
        </section>
      ) : (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-xl font-medium text-slate-900">Connexion QuickBooks Online</h2>
          <p className="mt-2 text-sm text-slate-600">Connecté en tant que {user.email}</p>

          <form className="mt-6 space-y-4 rounded-lg border border-slate-200 p-4" onSubmit={saveQboEmployee}>
            <h3 className="text-sm font-medium text-slate-900">Employé QuickBooks</h3>
            <p className="text-sm text-slate-600">
              Associez ce compte à un employé QBO pour la feuille de temps.
            </p>
            <label className="block text-sm font-medium text-slate-700">
              ID employé QBO
              <input
                required
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                value={qboEmployeeRef}
                onChange={(event) => setQboEmployeeRef(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium text-slate-700">
              Nom employé (optionnel)
              <input
                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                value={qboEmployeeName}
                onChange={(event) => setQboEmployeeName(event.target.value)}
              />
            </label>
            <button
              type="submit"
              disabled={savingEmployee}
              className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            >
              {savingEmployee ? 'Enregistrement...' : 'Enregistrer l\'employé'}
            </button>
          </form>

          {notice && (
            <p className="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{notice}</p>
          )}

          {error && (
            <p className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
          )}

          {status && (
            <p className="mt-4 text-sm text-slate-600">
              Statut :{' '}
              <span className="font-medium text-slate-900">
                {status.connected ? `Connecté (realm ${status.realm_id})` : 'Non connecté'}
              </span>
            </p>
          )}

          <div className="mt-6 flex gap-3">
            <button
              type="button"
              onClick={connectQuickBooks}
              disabled={connecting}
              className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
            >
              {connecting ? 'Redirection...' : 'Connecter QuickBooks'}
            </button>
            {status?.connected && (
              <button
                type="button"
                onClick={disconnectQuickBooks}
                className="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
              >
                Déconnecter QuickBooks
              </button>
            )}
          </div>
        </section>
      )}
    </main>
  )
}

export default App
