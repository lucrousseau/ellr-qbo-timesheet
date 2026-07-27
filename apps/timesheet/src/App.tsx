/**
 * @file Timesheet UI for creating and reviewing QuickBooks time activities.
 */

import { Alert, AppShell, LoadingScreen } from '@ellr/ui'
import { EmailVerificationBanner } from './components/EmailVerificationBanner'
import { QboEmployeeWarning } from './components/QboEmployeeWarning'
import { TimeEntryForm } from './components/TimeEntryForm'
import { TimesheetGuestAuth } from './components/TimesheetGuestAuth'
import { useTimesheetAuth } from './hooks/useTimesheetAuth'
import { useTimeEntry } from './hooks/useTimeEntry'

/**
 * Timesheet app: sign-in, password recovery, and QBO time activity entry.
 * @returns Loading screen, auth flows, or time form depending on session.
 */
function App() {
  const { form, setForm, submitting, message, clearMessage, submit } = useTimeEntry()
  const auth = useTimesheetAuth()

  if (auth.authLoading) {
    return <LoadingScreen />
  }

  if (!auth.user) {
    return <TimesheetGuestAuth auth={auth} message={message} />
  }

  const onLogout = async () => {
    clearMessage()
    await auth.handleLogout()
  }

  return (
    <AppShell title="Timesheet" userEmail={auth.user.email} onLogout={onLogout}>
      {auth.bootstrapError && (
        <div className="mb-4">
          <Alert variant="error">{auth.bootstrapError}</Alert>
        </div>
      )}
      {auth.showEmailVerification(auth.user) && (
        <EmailVerificationBanner
          message={auth.verificationMessage}
          messageVariant={auth.verificationMessageVariant}
          sending={auth.verificationSending}
          onResend={auth.handleResendVerification}
        />
      )}
      {!auth.user.qbo_employee_ref ? (
        <QboEmployeeWarning />
      ) : auth.showEmailVerification(auth.user) ? null : (
        <TimeEntryForm
          employeeLabel={
            auth.user.qbo_employee_name
              ? `${auth.user.qbo_employee_name} (${auth.user.qbo_employee_ref})`
              : String(auth.user.qbo_employee_ref)
          }
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
