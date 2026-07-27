/**
 * @file Set a new password from an email reset link.
 */

import type { FormEvent } from 'react'
import { Alert, cardClass, inputClass, pageMainClass, pageTitleClass, primaryButtonClass } from '@ellr/ui'

type ResetPasswordFormProps = {
  email: string
  password: string
  passwordConfirmation: string
  submitting: boolean
  error: string | null
  success: string | null
  invalidLink: boolean
  onPasswordChange: (value: string) => void
  onPasswordConfirmationChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
  onBackToLogin: () => void
}

/**
 * Collects a new password for a reset token from email.
 * @param props Controlled fields and submit handlers.
 * @returns Reset-password page content.
 */
export function ResetPasswordForm({
  email,
  password,
  passwordConfirmation,
  submitting,
  error,
  success,
  invalidLink,
  onPasswordChange,
  onPasswordConfirmationChange,
  onSubmit,
  onBackToLogin,
}: ResetPasswordFormProps) {
  return (
    <main className={pageMainClass}>
      <header className="mb-8">
        <h1 className={pageTitleClass}>Timesheet</h1>
        <p className="mt-2 text-slate-600">Choose a new password</p>
      </header>
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">Reset password</h2>

        {invalidLink && (
          <div className="mt-4">
            <Alert variant="error">This reset link is invalid or incomplete.</Alert>
          </div>
        )}

        {error && (
          <div className="mt-4">
            <Alert variant="error">{error}</Alert>
          </div>
        )}

        {success && (
          <div className="mt-4">
            <Alert variant="success">{success}</Alert>
          </div>
        )}

        {!invalidLink && !success && (
          <form className="mt-4 space-y-4" onSubmit={onSubmit}>
            <p className="text-sm text-slate-600">Resetting password for {email}</p>
            <label className="block text-sm font-medium text-slate-700">
              New password
              <input
                type="password"
                required
                className={inputClass}
                value={password}
                onChange={(event) => onPasswordChange(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium text-slate-700">
              Confirm password
              <input
                type="password"
                required
                className={inputClass}
                value={passwordConfirmation}
                onChange={(event) => onPasswordConfirmationChange(event.target.value)}
              />
            </label>
            <button type="submit" className={primaryButtonClass} disabled={submitting}>
              {submitting ? 'Saving…' : 'Update password'}
            </button>
          </form>
        )}

        <button
          type="button"
          className="mt-4 text-sm text-slate-600 underline"
          onClick={onBackToLogin}
        >
          Back to sign in
        </button>
      </section>
    </main>
  )
}
