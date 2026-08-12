/**
 * @file Ellr pastille and optional wordmark for product chrome.
 */

import { useLocale } from '../i18n/LocaleProvider'
import { EllrLogoGraphic } from './EllrLogoGraphic'

type EllrLogoMarkProps = {
  /** Show the lowercase wordmark beside the pastille. */
  showWordmark?: boolean
  /** Show the brand tagline under the wordmark (auth screens). */
  showTagline?: boolean
  /** Pastille size preset. */
  size?: 'sm' | 'md'
  className?: string
}

const pastilleSizeClass = {
  sm: 'h-[34px] w-[34px] rounded-[9px] [&_svg]:h-7 [&_svg]:w-7',
  md: 'h-11 w-11 rounded-[11px] [&_svg]:h-8 [&_svg]:w-8',
} as const

/**
 * Brand mark used in AppShell, auth screens, and loading states.
 * @param props Wordmark/tagline visibility and size.
 * @returns Pastille with optional wordmark column.
 */
export function EllrLogoMark({
  showWordmark = true,
  showTagline = false,
  size = 'sm',
  className = '',
}: EllrLogoMarkProps) {
  const { t } = useLocale()
  const name = t('brand.name')
  const tagline = t('brand.tagline')

  return (
    <span className={`inline-flex items-center gap-3 ${className}`.trim()}>
      <span
        className={`inline-flex shrink-0 items-center justify-center bg-brand-accent text-brand-on-accent ${pastilleSizeClass[size]}`}
        aria-hidden
      >
        <EllrLogoGraphic />
      </span>
      {showWordmark && (
        <span className="flex flex-col justify-center gap-0.5 leading-none">
          <span className="font-geo text-[1.65rem] font-medium tracking-[-0.01em] text-brand-primary">
            {name}
          </span>
          {showTagline && (
            <span className="text-[12px] font-normal tracking-[0.02em] text-brand-muted">{tagline}</span>
          )}
        </span>
      )}
      {!showWordmark && <span className="sr-only">{name}</span>}
    </span>
  )
}
