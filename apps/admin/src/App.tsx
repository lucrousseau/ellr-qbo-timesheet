/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
 */

import { AppShell, LoadingScreen, LocaleProvider, useLocale, useSyncUserLocale } from '@ellr/ui'
import { AdminDashboard } from './components/AdminDashboard'
import { AdminGuestAuth } from './components/AdminGuestAuth'
import { useQuickBooksAdmin } from './hooks/useQuickBooksAdmin'

/**
 * Authenticated admin dashboard with preferences and QuickBooks tools.
 * @returns Admin shell content when the session is ready.
 */
function AdminApp() {
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
      <AdminDashboard admin={admin} />
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
      <AdminApp />
    </LocaleProvider>
  )
}

export default App
