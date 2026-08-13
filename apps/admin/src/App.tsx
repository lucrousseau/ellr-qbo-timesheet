/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
 */

import { AppShell, BootstrapUnavailableScreen, LoadingScreen, LocaleProvider, useLocale, usePasswordResetInviteGate, useSyncUserLocale } from '@ellr/ui'
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
  const resetInviteGate = usePasswordResetInviteGate({
    authLoading: admin.authLoading,
    user: admin.user,
    handleLogout: admin.onLogout,
  })

  useSyncUserLocale(admin.user)

  if (admin.apiUnavailable) {
    return (
      <BootstrapUnavailableScreen onRetry={() => void admin.bootstrap()} retrying={admin.authLoading} />
    )
  }

  if (resetInviteGate.gateLoading) {
    return <LoadingScreen />
  }

  if (resetInviteGate.showGuestAuth) {
    return <AdminGuestAuth admin={admin} message={admin.message} />
  }

  return (
    <AppShell
      title={t('admin.appTitle')}
      subtitle={t('common.administration')}
      user={admin.user}
      onLogout={admin.onLogout}
      loggingOut={admin.loggingOut}
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
