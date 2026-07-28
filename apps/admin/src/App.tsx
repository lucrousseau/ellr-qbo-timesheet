/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
 */

import { AppShell, LoadingScreen, LocaleProvider, UserPreferencesPanel, useLocale, useSyncUserLocale } from '@ellr/ui'
import { AdminGuestAuth } from './components/AdminGuestAuth'
import { QuickBooksAdminPanel } from './components/QuickBooksAdminPanel'
import { useQuickBooksAdmin } from './hooks/useQuickBooksAdmin'

/**
 * Authenticated admin dashboard with preferences and QuickBooks tools.
 * @returns Admin shell content when the session is ready.
 */
function AdminDashboard() {
  const admin = useQuickBooksAdmin()
  const { t } = useLocale()

  useSyncUserLocale(admin.user)

  if (admin.authLoading) {
    return <LoadingScreen />
  }

  if (!admin.user) {
    return <AdminGuestAuth admin={admin} message={admin.message} />
  }

  return (
    <AppShell
      title={t('admin.appTitle')}
      subtitle={t('common.administration')}
      userEmail={admin.user.email}
      onLogout={admin.onLogout}
    >
      <div className="space-y-6">
        <UserPreferencesPanel
          locale={admin.preferenceLocale}
          saving={admin.savingLocale}
          onLocaleChange={admin.setPreferenceLocale}
          onSave={admin.saveLocale}
        />
        <QuickBooksAdminPanel
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
      </div>
    </AppShell>
  )
}

/**
 * Admin app: QuickBooks OAuth and QBO employee mapping.
 * @returns Locale-aware admin experience.
 */
function App() {
  return (
    <LocaleProvider>
      <AdminDashboard />
    </LocaleProvider>
  )
}

export default App
