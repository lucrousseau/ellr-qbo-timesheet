/**
 * @file User preferences form (language selection).
 */

import type { UserLocale } from '@ellr/api-client'
import { SUPPORTED_LOCALES } from '@ellr/api-client'
import { cardClass, secondaryButtonClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'

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
        <label className="block text-sm font-medium text-slate-700">
          {t('preferences.language')}
          <select
            className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
            value={locale}
            onChange={(event) => onLocaleChange(event.target.value as UserLocale)}
          >
            {SUPPORTED_LOCALES.map((supportedLocale) => (
              <option key={supportedLocale} value={supportedLocale}>
                {t(LOCALE_LABEL_KEYS[supportedLocale])}
              </option>
            ))}
          </select>
        </label>
        <button
          type="submit"
          disabled={saving}
          className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
        >
          {saving ? t('common.saving') : t('common.save')}
        </button>
      </form>
    </section>
  )
}
