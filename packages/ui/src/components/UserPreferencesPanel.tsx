/**
 * @file User preferences form (language and timezone).
 */

import {
  formatTimezoneLabel,
  SUPPORTED_LOCALES,
  timezonePreferenceOptions,
  type UserLocale,
} from '@ellr/api-client'
import { cardClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { Button } from './Button'
import { StaticSelect } from './StaticSelect'

type UserPreferencesPanelProps = {
  locale: UserLocale
  timezone: string
  companyTimezone?: string | null
  saving: boolean
  onLocaleChange: (locale: UserLocale) => void
  onTimezoneChange: (timezone: string) => void
  onSave: (event: React.FormEvent) => void
}

const LOCALE_LABEL_KEYS: Record<UserLocale, 'preferences.localeEn' | 'preferences.localeFr'> = {
  en: 'preferences.localeEn',
  fr: 'preferences.localeFr',
}

/**
 * Preferences card with language and timezone selection for the signed-in user.
 * @param props Controlled preference values and save handler.
 * @returns Preferences form section.
 */
export function UserPreferencesPanel({
  locale,
  timezone,
  companyTimezone,
  saving,
  onLocaleChange,
  onTimezoneChange,
  onSave,
}: UserPreferencesPanelProps) {
  const { t } = useLocale()
  const timezoneOptions = timezonePreferenceOptions(companyTimezone)

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('preferences.title')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('preferences.languageHelp')}</p>

      {companyTimezone ? (
        <p className="mt-2 text-sm text-slate-600">
          {t('preferences.companyTimezone', {
            timezone: formatTimezoneLabel(companyTimezone, locale),
          })}
        </p>
      ) : (
        <p className="mt-2 text-sm text-slate-600">{t('preferences.companyTimezonePending')}</p>
      )}

      <form className="mt-6 space-y-4" onSubmit={onSave}>
        <StaticSelect
          label={t('preferences.language')}
          value={locale}
          options={SUPPORTED_LOCALES}
          onChange={onLocaleChange}
          getOptionValue={(supportedLocale) => supportedLocale}
          getOptionLabel={(supportedLocale) => t(LOCALE_LABEL_KEYS[supportedLocale])}
        />
        <StaticSelect
          label={t('preferences.timezone')}
          value={timezone}
          options={timezoneOptions}
          onChange={onTimezoneChange}
          getOptionValue={(option) => option}
          getOptionLabel={(option) => {
            const label = formatTimezoneLabel(option, locale)

            if (companyTimezone && option === companyTimezone) {
              return `${label} (${t('preferences.timezoneCompanyDefault')})`
            }

            return label
          }}
        />
        <p className="text-sm text-slate-600">{t('preferences.timezoneHelp')}</p>
        <Button type="submit" variant="secondary" disabled={saving}>
          {saving ? t('common.saving') : t('common.save')}
        </Button>
      </form>
    </section>
  )
}
