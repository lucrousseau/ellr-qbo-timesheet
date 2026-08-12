/**
 * @file In-app mailto link so customers can contact ellr support.
 */

import { useLocale } from '../i18n/LocaleProvider'
import { linkButtonClass } from '../styles/tokens'
import { supportEmailForLocale, supportMailtoHref } from '../supportContact'

type SupportContactLinkProps = {
  className?: string
}

/**
 * Locale-aware support mailto link for authenticated and guest screens.
 * @param props Optional extra class names for layout.
 * @returns Accessible mailto anchor.
 */
export function SupportContactLink({ className }: SupportContactLinkProps) {
  const { locale, t } = useLocale()
  const email = supportEmailForLocale(locale)

  return (
    <a
      className={className ? `${linkButtonClass} ${className}` : linkButtonClass}
      href={supportMailtoHref(locale)}
    >
      {t('common.contactSupport', { email })}
    </a>
  )
}
