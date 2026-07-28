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
  savingLocale: boolean
  onLocaleChange: (locale: UserLocale) => void
  onSaveLocale: (event: React.FormEvent) => void
  tabIdPrefix?: string
}

/**
 * Account settings: preferences and password management.
 * @param props Locale preference handlers and optional tab id prefix.
 * @returns Preferences tab content.
 */
export function AccountPanel({
  preferenceLocale,
  savingLocale,
  onLocaleChange,
  onSaveLocale,
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
        saving={savingLocale}
        onLocaleChange={onLocaleChange}
        onSave={onSaveLocale}
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
