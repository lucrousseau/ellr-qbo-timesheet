import { useEffect, useState } from 'react'
import {
  apiFetch,
  fetchCurrentUser,
  getApiErrorMessage,
  login,
  logout,
  type User,
} from '@ellr/api-client'

type TimeActivityPayload = {
  employee_ref: string
  employee_name?: string
  customer_ref?: string
  start_time: string
  end_time: string
  description?: string
}

type Message = {
  text: string
  type: 'success' | 'error'
}

function App() {
  const [user, setUser] = useState<User | null>(null)
  const [authLoading, setAuthLoading] = useState(true)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [form, setForm] = useState<TimeActivityPayload>({
    employee_ref: '',
    employee_name: '',
    start_time: '',
    end_time: '',
    description: '',
  })
  const [message, setMessage] = useState<Message | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    fetchCurrentUser()
      .then(setUser)
      .catch(() => setMessage({ text: 'Impossible de joindre l\'API Laravel.', type: 'error' }))
      .finally(() => setAuthLoading(false))
  }, [])

  const handleLogin = async (event: React.FormEvent) => {
    event.preventDefault()
    setMessage(null)

    try {
      const loggedInUser = await login(email, password)
      setUser(loggedInUser)
    } catch (caught) {
      setMessage({ text: getApiErrorMessage(caught, 'Connexion impossible.'), type: 'error' })
    }
  }

  const handleLogout = async () => {
    try {
      await logout()
      setUser(null)
    } catch (caught) {
      setMessage({ text: getApiErrorMessage(caught, 'Déconnexion impossible.'), type: 'error' })
    }
  }

  const buildTimeActivityPayload = (): TimeActivityPayload => {
    const payload: TimeActivityPayload = {
      employee_ref: form.employee_ref,
      start_time: form.start_time,
      end_time: form.end_time,
    }

    if (form.employee_name) {
      payload.employee_name = form.employee_name
    }

    if (form.description) {
      payload.description = form.description
    }

    return payload
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setSubmitting(true)

    try {
      await apiFetch('/time-activities', {
        method: 'POST',
        body: JSON.stringify(buildTimeActivityPayload()),
      })
      setMessage({ text: 'Temps enregistré dans QuickBooks Online.', type: 'success' })
    } catch (caught) {
      setMessage({
        text: getApiErrorMessage(caught, 'Erreur lors de l\'enregistrement.'),
        type: 'error',
      })
    } finally {
      setSubmitting(false)
    }
  }

  const inputClass =
    'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200'

  if (authLoading) {
    return (
      <main className="mx-auto max-w-3xl px-6 py-10">
        <p className="text-slate-600">Chargement...</p>
      </main>
    )
  }

  if (!user) {
    return (
      <main className="mx-auto max-w-3xl px-6 py-10">
        <header className="mb-8">
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Feuille de temps</h1>
          <p className="mt-2 text-slate-600">Connectez-vous pour enregistrer votre temps</p>
        </header>
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <form className="space-y-4" onSubmit={handleLogin}>
            <label className="block text-sm font-medium text-slate-700">
              Courriel
              <input
                type="email"
                required
                className={inputClass}
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium text-slate-700">
              Mot de passe
              <input
                type="password"
                required
                className={inputClass}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </label>
            {message && (
              <p
                className={`rounded-lg px-4 py-3 text-sm ${
                  message.type === 'error' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-800'
                }`}
              >
                {message.text}
              </p>
            )}
            <button
              type="submit"
              className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800"
            >
              Se connecter
            </button>
          </form>
        </section>
      </main>
    )
  }

  return (
    <main className="mx-auto max-w-3xl px-6 py-10">
      <header className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Feuille de temps</h1>
          <p className="mt-2 text-slate-600">Connecté en tant que {user.email}</p>
        </div>
        <button
          type="button"
          onClick={handleLogout}
          className="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
        >
          Déconnexion
        </button>
      </header>

      <form
        className="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        onSubmit={submit}
      >
        <label className="block text-sm font-medium text-slate-700">
          ID employé QBO
          <input
            required
            className={inputClass}
            value={form.employee_ref}
            onChange={(event) => setForm({ ...form, employee_ref: event.target.value })}
          />
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Nom employé
          <input
            className={inputClass}
            value={form.employee_name}
            onChange={(event) => setForm({ ...form, employee_name: event.target.value })}
          />
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Début
          <input
            type="datetime-local"
            required
            className={inputClass}
            value={form.start_time}
            onChange={(event) => setForm({ ...form, start_time: event.target.value })}
          />
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Fin
          <input
            type="datetime-local"
            required
            className={inputClass}
            value={form.end_time}
            onChange={(event) => setForm({ ...form, end_time: event.target.value })}
          />
        </label>

        <label className="block text-sm font-medium text-slate-700">
          Description
          <textarea
            rows={4}
            className={inputClass}
            value={form.description}
            onChange={(event) => setForm({ ...form, description: event.target.value })}
          />
        </label>

        <button
          type="submit"
          disabled={submitting}
          className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
        >
          {submitting ? 'Enregistrement...' : 'Enregistrer'}
        </button>

        {message && (
          <p
            className={`rounded-lg px-4 py-3 text-sm ${
              message.type === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'
            }`}
          >
            {message.text}
          </p>
        )}
      </form>
    </main>
  )
}

export default App
