/**
 * @file Full-page loading placeholder while auth state resolves.
 */

import { pageMainClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'

/**
 * Full-page screen shown while the session is bootstrapping.
 * @returns Minimal loading indicator.
 */
export function LoadingScreen() {
  const { t } = useLocale()

  return (
    <main className={pageMainClass}>
      <p className="text-slate-600">{t('common.loading')}</p>
    </main>
  )
}
