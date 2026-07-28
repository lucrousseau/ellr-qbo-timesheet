/**
 * @file User preferences form (language selection).
 */

import type { UserLocale } from '@ellr/api-client'
import { SUPPORTED_LOCALES } from '@ellr/api-client'
import { cardClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { Button } from './Button'
import { StaticSelect } from './StaticSelect'

type UserPreferencesPanelProps = {
  locale: UserLocale
  saving: boolean
  onLocaleChange: (locale: UserLocale) => void
  onSave: (event: React.FormEvent) => void
}

const LOCALE_LABEL_KEYS: Record<UserLocale, 'preferences.localeEn' | 'preferences.localeFr'> = {
  en: 'preferences.localeEn',
  fr: 'preferences.localeFr',
}

/**
 * Preferences card with language selection for the signed-in user.
 * @param props.locale Controlled locale value.
 * @param props.saving Whether a save request is in flight.
 * @param props.onLocaleChange Updates the controlled locale value.
 * @param props.onSave Persists preferences on submit.
 * @returns Preferences form section.
 */
export function UserPreferencesPanel({
  locale,
  saving,
  onLocaleChange,
  onSave,
}: UserPreferencesPanelProps) {
  const { t } = useLocale()

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('preferences.title')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('preferences.languageHelp')}</p>

      <form className="mt-6 space-y-4" onSubmit={onSave}>
        <StaticSelect
          label={t('preferences.language')}
          value={locale}
          options={SUPPORTED_LOCALES}
          onChange={onLocaleChange}
          getOptionValue={(supportedLocale) => supportedLocale}
          getOptionLabel={(supportedLocale) => t(LOCALE_LABEL_KEYS[supportedLocale])}
        />
        <Button type="submit" variant="secondary" disabled={saving}>
          {saving ? t('common.saving') : t('common.save')}
        </Button>
      </form>
    </section>
  )
}
