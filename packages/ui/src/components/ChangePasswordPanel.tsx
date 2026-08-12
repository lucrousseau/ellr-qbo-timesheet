/**
 * @file Signed-in password change form with policy requirements.
 */

import type { FormEvent } from 'react'
import { PasswordPolicyRequirements } from './PasswordPolicyRequirements'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { Button } from './Button'
import { TextField } from './TextField'
import { cardClass, sectionHelpClass, sectionTitleClass } from '../styles/tokens'

type ChangePasswordPanelProps = {
  currentPassword: string
  password: string
  passwordConfirmation: string
  saving: boolean
  error: string | null
  success: string | null
  onCurrentPasswordChange: (value: string) => void
  onPasswordChange: (value: string) => void
  onPasswordConfirmationChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
}

/**
 * Collects the current and new passwords for an authenticated session.
 * @param props Controlled fields and submit handlers.
 * @returns Change-password card section.
 */
export function ChangePasswordPanel({
  currentPassword,
  password,
  passwordConfirmation,
  saving,
  error,
  success,
  onCurrentPasswordChange,
  onPasswordChange,
  onPasswordConfirmationChange,
  onSubmit,
}: ChangePasswordPanelProps) {
  const { t } = useLocale()

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleClass}>{t('auth.changePasswordTitle')}</h2>
      <p className={sectionHelpClass}>{t('auth.changePasswordHelp')}</p>

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

      <form className="mt-6 space-y-4" onSubmit={onSubmit}>
        <PasswordPolicyRequirements />
        <TextField
          label={t('auth.currentPassword')}
          type="password"
          required
          autoComplete="current-password"
          value={currentPassword}
          onChange={(event) => onCurrentPasswordChange(event.target.value)}
        />
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
        <Button type="submit" variant="secondary" disabled={saving}>
          {saving ? t('common.saving') : t('auth.updatePassword')}
        </Button>
      </form>
    </section>
  )
}
