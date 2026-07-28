/**
 * @file Sign-in, forgot password, and reset password screens for admin guests.
 */

import { Alert, ForgotPasswordForm, LoginForm, ResetPasswordForm } from '@ellr/ui'
import type { FlashMessage } from '@ellr/ui'
import type { useQuickBooksAdmin } from '../hooks/useQuickBooksAdmin'

const ADMIN_PAGE_TITLE = 'Ellr QBO Timesheet'
const ADMIN_PAGE_SUBTITLE = 'Administration'

type AdminGuestAuthProps = {
  admin: ReturnType<typeof useQuickBooksAdmin>
  message: FlashMessage | null
}

/**
 * Renders guest authentication screens before a Sanctum session exists.
 * @param props Admin hook state and optional flash message.
 * @returns Login, forgot-password, or reset-password UI.
 */
export function AdminGuestAuth({ admin, message }: AdminGuestAuthProps) {
  if (admin.authScreen === 'forgot-password') {
    return (
      <ForgotPasswordForm
        title={ADMIN_PAGE_TITLE}
        subtitle={ADMIN_PAGE_SUBTITLE}
        email={admin.forgotEmail}
        submitting={admin.forgotSubmitting}
        error={admin.forgotError}
        success={admin.forgotSuccess}
        onEmailChange={admin.setForgotEmail}
        onSubmit={admin.handleForgotPassword}
        onBackToLogin={admin.goToLogin}
      />
    )
  }

  if (admin.authScreen === 'reset-password') {
    return (
      <ResetPasswordForm
        title={ADMIN_PAGE_TITLE}
        subtitle={ADMIN_PAGE_SUBTITLE}
        email={admin.resetParams.email ?? ''}
        password={admin.resetPasswordValue}
        passwordConfirmation={admin.resetPasswordConfirmation}
        submitting={admin.resetSubmitting}
        error={admin.resetError}
        success={admin.resetSuccess}
        invalidLink={admin.resetLinkInvalid}
        onPasswordChange={admin.setResetPasswordValue}
        onPasswordConfirmationChange={admin.setResetPasswordConfirmation}
        onSubmit={admin.handleResetPassword}
        onBackToLogin={admin.goToLogin}
      />
    )
  }

  return (
    <LoginForm
      title={ADMIN_PAGE_TITLE}
      subtitle={ADMIN_PAGE_SUBTITLE}
      email={admin.email}
      password={admin.password}
      onEmailChange={admin.setEmail}
      onPasswordChange={admin.setPassword}
      onSubmit={admin.onLogin}
      error={admin.bootstrapError}
      footer={
        <>
          {message ? (
            <div className="mt-4">
              <Alert variant={message.type}>{message.text}</Alert>
            </div>
          ) : null}
          <button
            type="button"
            className="mt-4 text-sm text-slate-600 underline"
            onClick={() => admin.setAuthScreen('forgot-password')}
          >
            Forgot password?
          </button>
        </>
      }
    />
  )
}
