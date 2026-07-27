/**
 * @file Admin UI for QuickBooks OAuth connection and QBO employee mapping.
 */

import { Alert, AppShell, LoadingScreen, LoginForm } from '@ellr/ui'
import { QuickBooksAdminPanel } from './components/QuickBooksAdminPanel'
import { useQuickBooksAdmin } from './hooks/useQuickBooksAdmin'

/**
 * Admin app: QuickBooks OAuth and QBO employee mapping.
 * @returns Loading screen, sign-in, or admin dashboard depending on session.
 */
function App() {
  const {
    user,
    authLoading,
    email,
    setEmail,
    password,
    setPassword,
    bootstrapError,
    handleLogin,
    onLogout,
    message,
    status,
    connecting,
    qboEmployeeRef,
    setQboEmployeeRef,
    qboEmployeeName,
    setQboEmployeeName,
    savingEmployee,
    connectQuickBooksFlow,
    disconnectQuickBooksFlow,
    saveQboEmployee,
  } = useQuickBooksAdmin()

  if (authLoading) {
    return <LoadingScreen />
  }

  if (!user) {
    return (
      <LoginForm
        title="Ellr QBO Timesheet"
        subtitle="Administration"
        email={email}
        password={password}
        onEmailChange={setEmail}
        onPasswordChange={setPassword}
        onSubmit={handleLogin}
        error={bootstrapError}
        footer={
          message ? (
            <Alert variant={message.type === 'error' ? 'error' : 'success'}>{message.text}</Alert>
          ) : undefined
        }
      />
    )
  }

  return (
    <AppShell
      title="Ellr QBO Timesheet"
      subtitle="Administration"
      userEmail={user.email}
      onLogout={onLogout}
    >
      <QuickBooksAdminPanel
        userEmail={user.email}
        bootstrapError={bootstrapError}
        message={message}
        status={status}
        connecting={connecting}
        qboEmployeeRef={qboEmployeeRef}
        qboEmployeeName={qboEmployeeName}
        savingEmployee={savingEmployee}
        onQboEmployeeRefChange={setQboEmployeeRef}
        onQboEmployeeNameChange={setQboEmployeeName}
        onSaveEmployee={saveQboEmployee}
        onConnect={connectQuickBooksFlow}
        onDisconnect={disconnectQuickBooksFlow}
      />
    </AppShell>
  )
}

export default App
