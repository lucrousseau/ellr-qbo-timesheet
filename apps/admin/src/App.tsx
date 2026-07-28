/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
 */

import { AppShell, LoadingScreen } from '@ellr/ui'
import { AdminGuestAuth } from './components/AdminGuestAuth'
import { QuickBooksAdminPanel } from './components/QuickBooksAdminPanel'
import { useQuickBooksAdmin } from './hooks/useQuickBooksAdmin'

/**
 * Admin app: QuickBooks OAuth and QBO employee mapping.
 * @returns Loading screen, sign-in, or admin dashboard depending on session.
 */
function App() {
  const admin = useQuickBooksAdmin()

  if (admin.authLoading) {
    return <LoadingScreen />
  }

  if (!admin.user) {
    return <AdminGuestAuth admin={admin} message={admin.message} />
  }

  return (
    <AppShell
      title="Ellr QBO Timesheet"
      subtitle="Administration"
      userEmail={admin.user.email}
      onLogout={admin.onLogout}
    >
      <QuickBooksAdminPanel
        userEmail={admin.user.email}
        bootstrapError={admin.bootstrapError}
        message={admin.message}
        status={admin.status}
        connecting={admin.connecting}
        disconnecting={admin.disconnecting}
        qboEmployeeRef={admin.qboEmployeeRef}
        qboEmployeeName={admin.qboEmployeeName}
        savingEmployee={admin.savingEmployee}
        onQboEmployeeRefChange={admin.setQboEmployeeRef}
        onQboEmployeeNameChange={admin.setQboEmployeeName}
        onSaveEmployee={admin.saveQboEmployee}
        onConnect={admin.connectQuickBooksFlow}
        onDisconnect={admin.disconnectQuickBooksFlow}
      />
    </AppShell>
  )
}

export default App
