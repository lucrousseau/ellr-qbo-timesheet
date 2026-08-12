/**
 * @file Shared application shell with brand mark, header, and layout chrome.
 */

import type { ReactNode } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { pageMainClass, pageSubtitleClass, pageTitleClass } from '../styles/tokens'
import { Button } from './Button'
import { EllrLogoMark } from './EllrLogoMark'

type AppShellProps = {
  title: string
  subtitle?: string
  userEmail?: string | null
  onLogout?: () => void
  loggingOut?: boolean
  children: ReactNode
}

/**
 * Authenticated page shell with brand mark, header, and optional sign-out.
 * @param props.title Main application title.
 * @param props.subtitle Optional subtitle below the title.
 * @param props.userEmail Email shown with sign-out when provided.
 * @param props.onLogout Sign-out callback.
 * @param props.children Main page content.
 * @returns Centered layout with header.
 */
export function AppShell({ title, subtitle, userEmail, onLogout, loggingOut = false, children }: AppShellProps) {
  const { t } = useLocale()

  return (
    <main className={pageMainClass}>
      <header className="mb-8 flex items-start justify-between gap-4">
        <div>
          <EllrLogoMark />
          <h1 className={`mt-6 ${pageTitleClass}`}>{title}</h1>
          {subtitle && <p className={pageSubtitleClass}>{subtitle}</p>}
          {userEmail && !subtitle && (
            <p className={pageSubtitleClass}>{t('common.signedInAs', { email: userEmail })}</p>
          )}
        </div>
        {userEmail && onLogout && (
          <Button
            type="button"
            variant="secondary"
            size="compact"
            onClick={onLogout}
            disabled={loggingOut}
          >
            {loggingOut ? t('common.signingOut') : t('common.signOut')}
          </Button>
        )}
      </header>
      {children}
    </main>
  )
}
