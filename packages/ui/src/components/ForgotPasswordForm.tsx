/**
 * @file Request a password reset link by email.
 */

import type { FormEvent } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { cardClass, inputClass, pageMainClass, pageTitleClass, primaryButtonClass } from '../styles/tokens'

type ForgotPasswordFormProps = {
  title: string
  subtitle?: string
  email: string
  submitting: boolean
  error: string | null
  success: string | null
  onEmailChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
  onBackToLogin: () => void
}

/**
 * Collects an email address and requests a password reset link.
 * @param props Controlled email field and submit handlers.
 * @returns Forgot-password page content.
 */
export function ForgotPasswordForm({
  title,
  subtitle,
  email,
  submitting,
  error,
  success,
  onEmailChange,
  onSubmit,
  onBackToLogin,
}: ForgotPasswordFormProps) {
  const { t } = useLocale()

  return (
    <main className={pageMainClass}>
      <header className="mb-8">
        <h1 className={pageTitleClass}>{title}</h1>
        <p className="mt-2 text-slate-600">{subtitle ?? t('auth.forgotPasswordSubtitle')}</p>
      </header>
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">{t('auth.forgotPasswordTitle')}</h2>

        {error ? (
          <div className="mt-4">
            <Alert variant="error">{error}</Alert>
          </div>
        ) : null}

        {success ? (
          <div className="mt-4">
            <Alert variant="success">{success}</Alert>
          </div>
        ) : null}

        <form className="mt-4 space-y-4" onSubmit={onSubmit}>
          <label className="block text-sm font-medium text-slate-700">
            {t('common.email')}
            <input
              type="email"
              required
              className={inputClass}
              value={email}
              onChange={(event) => onEmailChange(event.target.value)}
            />
          </label>
          <button type="submit" className={primaryButtonClass} disabled={submitting}>
            {submitting ? t('auth.sending') : t('auth.sendResetLink')}
          </button>
        </form>

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
