import { useState } from 'react'
import { createTimeActivity, getApiErrorMessage } from '@ellr/api-client'
import {
  Alert,
  AppShell,
  cardClass,
  inputClass,
  LoadingScreen,
  LoginForm,
  primaryButtonClass,
  useAuth,
  useFlashMessage,
} from '@ellr/ui'

type TimeActivityForm = {
  start_time: string
  end_time: string
  description: string
}

function App() {
  const { message, clearMessage, showError, showSuccess } = useFlashMessage()
  const [form, setForm] = useState<TimeActivityForm>({
    start_time: '',
    end_time: '',
    description: '',
  })
  const [submitting, setSubmitting] = useState(false)

  const {
    user,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    handleLogin,
    handleLogout,
  } = useAuth()

  const buildPayload = () => {
    const payload: { start_time: string; end_time: string; description?: string } = {
      start_time: form.start_time,
      end_time: form.end_time,
    }

    if (form.description) {
      payload.description = form.description
    }

    return payload
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    clearMessage()
    setSubmitting(true)

    try {
      await createTimeActivity(buildPayload())
      showSuccess('Temps enregistré dans QuickBooks Online.')
    } catch (caught) {
      showError(getApiErrorMessage(caught, 'Erreur lors de l\'enregistrement.'))
    } finally {
      setSubmitting(false)
    }
  }

  if (authLoading) {
    return <LoadingScreen />
  }

  if (!user) {
    return (
      <LoginForm
        title="Feuille de temps"
        subtitle="Connectez-vous pour enregistrer votre temps"
        email={email}
        password={password}
        onEmailChange={setEmail}
        onPasswordChange={setPassword}
        onSubmit={handleLogin}
        error={bootstrapError}
        heading="Connexion"
        footer={
          message ? (
            <Alert variant={message.type === 'error' ? 'error' : 'success'}>{message.text}</Alert>
          ) : undefined
        }
      />
    )
  }

  const employeeLabel = user.qbo_employee_name
    ? `${user.qbo_employee_name} (${user.qbo_employee_ref})`
    : user.qbo_employee_ref

  return (
    <AppShell title="Feuille de temps" userEmail={user.email} onLogout={handleLogout}>
      {bootstrapError && (
        <div className="mb-4">
          <Alert variant="error">{bootstrapError}</Alert>
        </div>
      )}
      {!user.qbo_employee_ref ? (
        <Alert variant="warning">
          Employé QuickBooks non configuré. Un administrateur doit associer votre compte à un employé QBO.
        </Alert>
      ) : (
        <form className={`space-y-5 ${cardClass}`} onSubmit={submit}>
          <p className="text-sm text-slate-600">
            Employé QBO : <span className="font-medium text-slate-900">{employeeLabel}</span>
          </p>

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

          <button type="submit" disabled={submitting} className={primaryButtonClass}>
            {submitting ? 'Enregistrement...' : 'Enregistrer'}
          </button>

          {message && <Alert variant={message.type}>{message.text}</Alert>}
        </form>
      )}
    </AppShell>
  )
}

export default App
