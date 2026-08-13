/**
 * @file Email and password sign-in form for Sanctum session auth.
 */

import type { FormEvent, ReactNode } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { cardClass, sectionTitleClass } from '../styles/tokens'
import { Alert } from './Alert'
import { AuthPageLayout } from './AuthPageLayout'
import { Button } from './Button'
import { PasswordField } from './PasswordField'
import { TextField } from './TextField'

type LoginFormProps = {
  title: string
  subtitle?: string
  email: string
  password: string
  onEmailChange: (value: string) => void
  onPasswordChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
  submitting?: boolean
  error?: string | null
  heading?: string
  footer?: ReactNode
}

/**
 * Reusable Sanctum sign-in form (admin and timesheet).
 * @param props.title Page title.
 * @param props.subtitle Optional intro text.
 * @param props.email Controlled email field value.
 * @param props.password Controlled password field value.
 * @param props.onEmailChange Updates the email value.
 * @param props.onPasswordChange Updates the password value.
 * @param props.onSubmit Form submit handler (sign-in).
 * @param props.error Error message above the form.
 * @param props.heading Sign-in card heading.
 * @param props.footer Optional content below the button (e.g. flash alert).
 * @returns Complete sign-in page.
 */
export function LoginForm({
  title,
  subtitle,
  email,
  password,
  onEmailChange,
  onPasswordChange,
  onSubmit,
  submitting = false,
  error,
  heading,
  footer,
}: LoginFormProps) {
  const { t } = useLocale()

  return (
    <AuthPageLayout title={title} subtitle={subtitle}>
      <section className={cardClass}>
        <h2 className={sectionTitleClass}>{heading ?? t('common.signIn')}</h2>

        {error && (
          <div className="mt-4">
            <Alert variant="error">{error}</Alert>
          </div>
        )}

        <form className="mt-4 space-y-4" onSubmit={onSubmit}>
          <TextField
            label={t('common.email')}
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(event) => onEmailChange(event.target.value)}
          />
          <PasswordField
            label={t('common.password')}
            required
            autoComplete="current-password"
            value={password}
            onChange={(event) => onPasswordChange(event.target.value)}
          />
          <Button type="submit" disabled={submitting}>
            {submitting ? t('common.signingIn') : t('common.signIn')}
          </Button>
          {footer}
        </form>
      </section>
    </AuthPageLayout>
  )
}
