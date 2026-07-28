/**
 * @file Signed-in password change form with policy requirements.
 */

import type { FormEvent } from 'react'
import { getPasswordRequirementLabels } from '../i18n/passwordPolicyMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { Alert } from './Alert'
import { TextField } from './TextField'
import { cardClass, secondaryButtonClass } from '../styles/tokens'

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
  const { t, locale } = useLocale()
  const requirementLabels = getPasswordRequirementLabels(locale)

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('auth.changePasswordTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('auth.changePasswordHelp')}</p>

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
        <div className="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
          <p className="font-medium text-slate-700">{t('auth.passwordPolicy.requirementsTitle')}</p>
          <ul className="mt-2 list-disc space-y-1 pl-5">
            {requirementLabels.map((label) => (
              <li key={label}>{label}</li>
            ))}
          </ul>
        </div>
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
        <button
          type="submit"
          disabled={saving}
          className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
        >
          {saving ? t('common.saving') : t('auth.updatePassword')}
        </button>
      </form>
    </section>
  )
}
