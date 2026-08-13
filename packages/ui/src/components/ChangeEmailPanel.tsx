/**
 * @file Signed-in email change form with password confirmation.
 */

import type { FormEvent } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { Button } from './Button'
import { PasswordField } from './PasswordField'
import { TextField } from './TextField'
import { cardClass, sectionHelpClass, sectionTitleClass } from '../styles/tokens'

type ChangeEmailPanelProps = {
  currentEmail: string
  email: string
  currentPassword: string
  saving: boolean
  error: string | null
  success: string | null
  onEmailChange: (value: string) => void
  onCurrentPasswordChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
}

/**
 * Collects a new email address for an authenticated session.
 * @param props Controlled fields and submit handlers.
 * @returns Change-email card section.
 */
export function ChangeEmailPanel({
  currentEmail,
  email,
  currentPassword,
  saving,
  error,
  success,
  onEmailChange,
  onCurrentPasswordChange,
  onSubmit,
}: ChangeEmailPanelProps) {
  const { t } = useLocale()

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleClass}>{t('auth.changeEmailTitle')}</h2>
      <p className={sectionHelpClass}>{t('auth.changeEmailHelp')}</p>
      <p className={`${sectionHelpClass} mt-2`}>
        {t('auth.currentEmail')}: {currentEmail}
      </p>

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
        <TextField
          label={t('auth.newEmail')}
          type="email"
          required
          autoComplete="email"
          value={email}
          onChange={(event) => onEmailChange(event.target.value)}
        />
        <PasswordField
          label={t('auth.currentPassword')}
          required
          autoComplete="current-password"
          value={currentPassword}
          onChange={(event) => onCurrentPasswordChange(event.target.value)}
        />
        <Button type="submit" variant="secondary" disabled={saving}>
          {saving ? t('common.saving') : t('auth.updateEmail')}
        </Button>
      </form>
    </section>
  )
}
