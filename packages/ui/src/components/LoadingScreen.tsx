/**
 * @file Full-page or inline loading placeholder while state resolves.
 */

import { pageMainClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { EllrLogoMark } from './EllrLogoMark'

type LoadingScreenProps = {
  /** `page` includes brand mark and page chrome; `inline` is muted text only. */
  variant?: 'page' | 'inline'
}

/**
 * Loading indicator for bootstrap (`page`) or in-panel waits (`inline`).
 * @param props.variant Layout density; defaults to full-page brand chrome.
 * @returns Loading UI.
 */
export function LoadingScreen({ variant = 'page' }: LoadingScreenProps) {
  const { t } = useLocale()

  const content = (
    <div className={variant === 'page' ? 'flex flex-col items-start gap-4' : undefined}>
      {variant === 'page' ? <EllrLogoMark showWordmark={false} /> : null}
      <p className="text-brand-muted">{t('common.loading')}</p>
    </div>
  )

  if (variant === 'inline') {
    return content
  }

  return <main className={pageMainClass}>{content}</main>
}
