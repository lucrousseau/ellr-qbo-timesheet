/**
 * @file Full-page state when the API is temporarily unreachable at bootstrap.
 */

import { Button } from './Button'
import { pageMainClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'

type BootstrapUnavailableScreenProps = {
  onRetry: () => void
  retrying?: boolean
}

/**
 * Shown instead of the login form when bootstrap failed for a transient API error.
 * @param props Retry handler and optional in-flight retry flag.
 * @returns Reconnect UI with manual retry.
 */
export function BootstrapUnavailableScreen({ onRetry, retrying = false }: BootstrapUnavailableScreenProps) {
  const { t } = useLocale()

  return (
    <main className={pageMainClass}>
      <div className="mx-auto w-full max-w-md space-y-4 text-center">
        <h1 className="text-xl font-semibold text-slate-900">{t('common.apiUnavailableTitle')}</h1>
        <p className="text-slate-600">{t('common.apiUnavailableBody')}</p>
        <Button type="button" onClick={onRetry} disabled={retrying}>
          {retrying ? t('common.apiUnavailableRetrying') : t('common.apiUnavailableRetry')}
        </Button>
      </div>
    </main>
  )
}
