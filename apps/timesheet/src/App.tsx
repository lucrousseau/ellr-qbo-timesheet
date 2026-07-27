/**
 * @file Timesheet UI for creating and reviewing QuickBooks time activities.
 */

import { Alert, AppShell, LoadingScreen, LoginForm, useAuth } from '@ellr/ui'
import { QboEmployeeWarning } from './components/QboEmployeeWarning'
import { TimeEntryForm } from './components/TimeEntryForm'
import { useTimeEntry } from './hooks/useTimeEntry'

/**
 * Timesheet app: Sanctum sign-in and QBO time activity entry.
 * @returns Loading screen, sign-in, or time form depending on session.
 */
function App() {
  const { form, setForm, submitting, message, clearMessage, submit } = useTimeEntry()

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

  const onLogout = async () => {
    clearMessage()
    await handleLogout()
  }

  if (authLoading) {
    return <LoadingScreen />
  }

  if (!user) {
    return (
      <LoginForm
        title="Timesheet"
        subtitle="Sign in to record your time"
        email={email}
        password={password}
        onEmailChange={setEmail}
        onPasswordChange={setPassword}
        onSubmit={handleLogin}
        error={bootstrapError}
        heading="Sign in"
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
    : (user.qbo_employee_ref ?? '')

  return (
    <AppShell title="Timesheet" userEmail={user.email} onLogout={onLogout}>
      {bootstrapError && (
        <div className="mb-4">
          <Alert variant="error">{bootstrapError}</Alert>
        </div>
      )}
      {!user.qbo_employee_ref ? (
        <QboEmployeeWarning />
      ) : (
        <TimeEntryForm
          employeeLabel={employeeLabel}
          form={form}
          submitting={submitting}
          message={message}
          onFormChange={setForm}
          onSubmit={submit}
        />
      )}
    </AppShell>
  )
}

export default App
