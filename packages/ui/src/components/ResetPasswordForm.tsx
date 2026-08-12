/**
 * @file Set a new password from an email reset link.
 */

import type { FormEvent } from 'react'
import { PasswordPolicyRequirements } from './PasswordPolicyRequirements'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { AuthPageLayout } from './AuthPageLayout'
import { Button } from './Button'
import { TextField } from './TextField'
import { cardClass, sectionTitleClass } from '../styles/tokens'

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
    <AuthPageLayout title={title} subtitle={subtitle ?? t('auth.resetPasswordSubtitle')}>
      <section className={cardClass}>
        <h2 className={sectionTitleClass}>{t('auth.resetPasswordTitle')}</h2>

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
            <p className="text-sm text-brand-muted">{t('auth.resettingFor', { email })}</p>
            <PasswordPolicyRequirements />
            <TextField
              label={t('auth.newPassword')}
              type="password"
              required
              autoComplete="new-password"
              value={password}
              onChange={(event) => onPasswordChange(event.target.value)}
            />
            <TextField
              label={t('auth.confirmPassword')}
              type="password"
              required
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(event) => onPasswordConfirmationChange(event.target.value)}
            />
            <Button type="submit" disabled={submitting}>
              {submitting ? t('common.saving') : t('auth.updatePassword')}
            </Button>
          </form>
        )}

        <div className="mt-4">
          <Button type="button" variant="link" onClick={onBackToLogin}>
            {t('auth.backToSignIn')}
          </Button>
        </div>
      </section>
    </AuthPageLayout>
  )
}
