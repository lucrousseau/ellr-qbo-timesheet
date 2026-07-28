/**
 * @file Timesheet UI for creating and reviewing QuickBooks time activities.
 */

import { LoadingScreen, LocaleProvider, usePasswordResetInviteGate, useSyncUserLocale } from '@ellr/ui'
import { TimesheetDashboard } from './components/TimesheetDashboard'
import { TimesheetGuestAuth } from './components/TimesheetGuestAuth'
import { useTimesheetAuth } from './hooks/useTimesheetAuth'
import { useTimeTracker } from './hooks/useTimeTracker'

/**
 * Authenticated timesheet experience with locale sync from the user profile.
 * @returns Timesheet shell content when the session is ready.
 */
function TimesheetApp() {
  const auth = useTimesheetAuth()
  const resetInviteGate = usePasswordResetInviteGate({
    authLoading: auth.authLoading,
    user: auth.user,
    handleLogout: auth.handleLogout,
  })
  const trackerEnabled =
    !auth.authLoading &&
    !resetInviteGate.gateLoading &&
    !resetInviteGate.showGuestAuth &&
    Boolean(auth.user?.qbo_employee_ref)
  const tracker = useTimeTracker(trackerEnabled)

  useSyncUserLocale(auth.user)

  if (resetInviteGate.gateLoading) {
    return <LoadingScreen />
  }

  if (resetInviteGate.showGuestAuth) {
    return <TimesheetGuestAuth auth={auth} message={tracker.message} />
  }

  return <TimesheetDashboard auth={auth} tracker={tracker} />
}

/**
 * Timesheet app: sign-in, password recovery, and QBO time activity entry.
 * @returns Locale-aware timesheet experience.
 */
function App() {
  return (
    <LocaleProvider>
      <TimesheetApp />
    </LocaleProvider>
  )
}

export default App
