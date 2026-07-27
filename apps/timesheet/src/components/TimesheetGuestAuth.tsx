/**
 * @file Sign-in, forgot password, and reset password screens for guests.
 */

import { Alert, LoginForm } from '@ellr/ui'
import type { FlashMessage } from '@ellr/ui'
import { ForgotPasswordForm } from './ForgotPasswordForm'
import { ResetPasswordForm } from './ResetPasswordForm'
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
  if (auth.authScreen === 'forgot-password') {
    return (
      <ForgotPasswordForm
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
      title="Timesheet"
      subtitle="Sign in to record your time"
      email={auth.email}
      password={auth.password}
      onEmailChange={auth.setEmail}
      onPasswordChange={auth.setPassword}
      onSubmit={auth.handleLogin}
      error={auth.bootstrapError}
      heading="Sign in"
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
          <button
            type="button"
            className="mt-4 text-sm text-slate-600 underline"
            onClick={() => auth.setAuthScreen('forgot-password')}
          >
            Forgot password?
          </button>
        </>
      }
    />
  )
}
