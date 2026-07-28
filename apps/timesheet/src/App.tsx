/**
 * @file Timesheet UI for creating and reviewing QuickBooks time activities.
 */

import { Alert, AppShell, LoadingScreen, LocaleProvider, UserPreferencesPanel, useLocale, usePasswordResetInviteGate, useSyncUserLocale } from '@ellr/ui'
import { EmailVerificationBanner } from './components/EmailVerificationBanner'
import { QboEmployeeWarning } from './components/QboEmployeeWarning'
import { TimeEntryForm } from './components/TimeEntryForm'
import { TimesheetGuestAuth } from './components/TimesheetGuestAuth'
import { useTimesheetAuth } from './hooks/useTimesheetAuth'
import { useTimeEntry } from './hooks/useTimeEntry'

/**
 * Authenticated timesheet experience with locale sync from the user profile.
 * @returns Timesheet shell content when the session is ready.
 */
function TimesheetDashboard() {
  const { form, setForm, submitting, message, clearMessage, submit } = useTimeEntry()
  const auth = useTimesheetAuth()
  const { t } = useLocale()
  const resetInviteGate = usePasswordResetInviteGate({
    authLoading: auth.authLoading,
    user: auth.user,
    handleLogout: auth.handleLogout,
  })

  useSyncUserLocale(auth.user)

  if (resetInviteGate.gateLoading) {
    return <LoadingScreen />
  }

  if (resetInviteGate.showGuestAuth) {
    return <TimesheetGuestAuth auth={auth} message={message} />
  }

  const onLogout = async () => {
    clearMessage()
    await auth.handleLogout()
  }

  return (
    <AppShell title={t('timesheet.appTitle')} userEmail={auth.user!.email} onLogout={onLogout}>
      {auth.bootstrapError && (
        <div className="mb-4">
          <Alert variant="error">{auth.bootstrapError}</Alert>
        </div>
      )}
      {auth.preferenceNotice && (
        <div className="mb-4">
          <Alert variant={auth.preferenceNoticeVariant === 'error' ? 'error' : 'success'}>
            {auth.preferenceNotice}
          </Alert>
        </div>
      )}
      <div className="mb-6">
        <UserPreferencesPanel
          locale={auth.preferenceLocale}
          saving={auth.savingLocale}
          onLocaleChange={auth.setPreferenceLocale}
          onSave={auth.saveLocale}
        />
      </div>
      {auth.showEmailVerification(auth.user!) && (
        <EmailVerificationBanner
          message={auth.verificationMessage}
          messageVariant={auth.verificationMessageVariant}
          sending={auth.verificationSending}
          onResend={auth.handleResendVerification}
        />
      )}
      {!auth.user!.qbo_employee_ref ? (
        <QboEmployeeWarning />
      ) : auth.showEmailVerification(auth.user!) ? null : (
        <TimeEntryForm
          employeeLabel={
            auth.user!.qbo_employee_name
              ? `${auth.user!.qbo_employee_name} (${auth.user!.qbo_employee_ref})`
              : String(auth.user!.qbo_employee_ref)
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

/**
 * Timesheet app: sign-in, password recovery, and QBO time activity entry.
 * @returns Locale-aware timesheet experience.
 */
function App() {
  return (
    <LocaleProvider>
      <TimesheetDashboard />
    </LocaleProvider>
  )
}

export default App
