/**
 * @file Account settings tab: language preferences and password change.
 */

import type { UserLocale } from '@ellr/ui'
import {
  ChangePasswordPanel,
  UserPreferencesPanel,
  tabButtonId,
  tabPanelId,
  useChangePassword,
} from '@ellr/ui'

type AccountPanelProps = {
  preferenceLocale: UserLocale
  preferenceTimezone: string
  companyTimezone?: string | null
  savingPreferences: boolean
  onLocaleChange: (locale: UserLocale) => void
  onTimezoneChange: (timezone: string) => void
  onSavePreferences: (event: React.FormEvent) => void
  tabIdPrefix?: string
}

/**
 * Account settings: preferences and password management.
 * @param props Preference handlers and optional tab id prefix.
 * @returns Preferences tab content.
 */
export function AccountPanel({
  preferenceLocale,
  preferenceTimezone,
  companyTimezone,
  savingPreferences,
  onLocaleChange,
  onTimezoneChange,
  onSavePreferences,
  tabIdPrefix = 'admin',
}: AccountPanelProps) {
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
