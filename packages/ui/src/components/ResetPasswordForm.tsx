/**
 * @file Set a new password from an email reset link.
 */

import type { FormEvent } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { cardClass, inputClass, pageMainClass, pageTitleClass, primaryButtonClass } from '../styles/tokens'

type ResetPasswordFormProps = {
  title: string
  subtitle?: string
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
  title,
  subtitle,
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
  const { t } = useLocale()

  return (
    <main className={pageMainClass}>
      <header className="mb-8">
        <h1 className={pageTitleClass}>{title}</h1>
        <p className="mt-2 text-slate-600">{subtitle ?? t('auth.resetPasswordSubtitle')}</p>
      </header>
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">{t('auth.resetPasswordTitle')}</h2>

        {invalidLink && (
          <div className="mt-4">
            <Alert variant="error">{t('auth.invalidResetLink')}</Alert>
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
            <p className="text-sm text-slate-600">{t('auth.resettingFor', { email })}</p>
            <label className="block text-sm font-medium text-slate-700">
              {t('auth.newPassword')}
              <input
                type="password"
                required
                className={inputClass}
                value={password}
                onChange={(event) => onPasswordChange(event.target.value)}
              />
            </label>
            <label className="block text-sm font-medium text-slate-700">
              {t('auth.confirmPassword')}
              <input
                type="password"
                required
                className={inputClass}
                value={passwordConfirmation}
                onChange={(event) => onPasswordConfirmationChange(event.target.value)}
              />
            </label>
            <button type="submit" className={primaryButtonClass} disabled={submitting}>
              {submitting ? t('common.saving') : t('auth.updatePassword')}
            </button>
          </form>
        )}

        <button
          type="button"
          className="mt-4 text-sm text-slate-600 underline"
          onClick={onBackToLogin}
        >
          {t('auth.backToSignIn')}
        </button>
      </section>
    </main>
  )
}
