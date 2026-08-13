/**
 * @file Account settings tab: language preferences, email, and password change.
 */

import type { User } from '@ellr/api-client'
import type { UserLocale } from '@ellr/ui'
import {
  ChangeEmailPanel,
  ChangePasswordPanel,
  UserPreferencesPanel,
  tabButtonId,
  tabPanelId,
  useChangeEmail,
  useChangePassword,
} from '@ellr/ui'

type AccountPanelProps = {
  currentEmail: string
  preferenceLocale: UserLocale
  preferenceTimezone: string
  companyTimezone?: string | null
  savingPreferences: boolean
  onLocaleChange: (locale: UserLocale) => void
  onTimezoneChange: (timezone: string) => void
  onSavePreferences: (event: React.FormEvent) => void
  onUserUpdated?: (user: User) => void
  tabIdPrefix?: string
}

/**
 * Account settings: preferences, email, and password management.
 * @param props Preference handlers and optional tab id prefix.
 * @returns Preferences tab content.
 */
export function AccountPanel({
  currentEmail,
  preferenceLocale,
  preferenceTimezone,
  companyTimezone,
  savingPreferences,
  onLocaleChange,
  onTimezoneChange,
  onSavePreferences,
  onUserUpdated,
  tabIdPrefix = 'admin',
}: AccountPanelProps) {
  const email = useChangeEmail({
    currentEmail,
    onSuccess: onUserUpdated,
  })
  const password = useChangePassword()
  const panelId = tabPanelId(tabIdPrefix, 'preferences')

  return (
    <div
      id={panelId}
      className="space-y-6"
      role="tabpanel"
      aria-labelledby={tabButtonId(tabIdPrefix, 'preferences')}
    >
      <UserPreferencesPanel
        locale={preferenceLocale}
        timezone={preferenceTimezone}
        companyTimezone={companyTimezone}
        saving={savingPreferences}
        onLocaleChange={onLocaleChange}
        onTimezoneChange={onTimezoneChange}
        onSave={onSavePreferences}
      />
      <ChangeEmailPanel
        currentEmail={currentEmail}
        email={email.email}
        currentPassword={email.currentPassword}
        saving={email.saving}
        error={email.error}
        success={email.success}
        onEmailChange={email.setEmail}
        onCurrentPasswordChange={email.setCurrentPassword}
        onSubmit={email.handleSubmit}
      />
      <ChangePasswordPanel
        currentPassword={password.currentPassword}
        password={password.password}
        passwordConfirmation={password.passwordConfirmation}
        saving={password.saving}
        error={password.error}
        success={password.success}
        onCurrentPasswordChange={password.setCurrentPassword}
        onPasswordChange={password.setPassword}
        onPasswordConfirmationChange={password.setPasswordConfirmation}
        onSubmit={password.handleSubmit}
      />
    </div>
  )
}
