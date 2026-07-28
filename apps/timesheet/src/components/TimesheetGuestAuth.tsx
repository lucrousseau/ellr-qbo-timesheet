/**
 * @file Sign-in, forgot password, and reset password screens for guests.
 */

import { Alert, Button, ForgotPasswordForm, LoginForm, ResetPasswordForm, useLocale, type FlashMessage } from '@ellr/ui'
import type { useTimesheetAuth } from '../hooks/useTimesheetAuth'

type TimesheetGuestAuthProps = {
  auth: ReturnType<typeof useTimesheetAuth>
  message: FlashMessage | null
}

/**
 * Renders guest authentication screens before a Sanctum session exists.
 * @param props Timesheet auth hook state and optional flash message.
 * @returns Login, forgot-password, or reset-password UI.
 */
export function TimesheetGuestAuth({ auth, message }: TimesheetGuestAuthProps) {
  const { t } = useLocale()

  if (auth.authScreen === 'forgot-password') {
    return (
      <ForgotPasswordForm
        title={t('timesheet.appTitle')}
        email={auth.forgotEmail}
        submitting={auth.forgotSubmitting}
        error={auth.forgotError}
        success={auth.forgotSuccess}
        onEmailChange={auth.setForgotEmail}
        onSubmit={auth.handleForgotPassword}
        onBackToLogin={auth.goToLogin}
      />
    )
  }

  if (auth.authScreen === 'reset-password') {
    return (
      <ResetPasswordForm
        title={t('timesheet.appTitle')}
        email={auth.resetParams.email ?? ''}
        password={auth.resetPasswordValue}
        passwordConfirmation={auth.resetPasswordConfirmation}
        submitting={auth.resetSubmitting}
        error={auth.resetError}
        success={auth.resetSuccess}
        invalidLink={auth.resetLinkInvalid}
        onPasswordChange={auth.setResetPasswordValue}
        onPasswordConfirmationChange={auth.setResetPasswordConfirmation}
        onSubmit={auth.handleResetPassword}
        onBackToLogin={auth.goToLogin}
      />
    )
  }

  return (
    <LoginForm
      title={t('timesheet.appTitle')}
      subtitle={t('timesheet.signInSubtitle')}
      email={auth.email}
      password={auth.password}
      onEmailChange={auth.setEmail}
      onPasswordChange={auth.setPassword}
      onSubmit={auth.handleLogin}
      submitting={auth.loggingIn}
      error={auth.bootstrapError}
      footer={
        <>
          {auth.loginNotice && (
            <div className="mt-4">
              <Alert variant="success">{auth.loginNotice}</Alert>
            </div>
          )}
          {message ? (
            <div className="mt-4">
              <Alert variant={message.type === 'error' ? 'error' : 'success'}>{message.text}</Alert>
            </div>
          ) : null}
          <div className="mt-4">
            <Button type="button" variant="link" onClick={() => auth.setAuthScreen('forgot-password')}>
              {t('auth.forgotPassword')}
            </Button>
          </div>
        </>
      }
    />
  )
}
