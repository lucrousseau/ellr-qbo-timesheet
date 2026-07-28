/**
 * @file Shared application shell with header, sign-out, and layout chrome.
 */

import type { ReactNode } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { pageMainClass, pageTitleClass } from '../styles/tokens'
import { Button } from './Button'

type AppShellProps = {
  title: string
  subtitle?: string
  userEmail?: string | null
  onLogout?: () => void
  loggingOut?: boolean
  children: ReactNode
}

/**
 * Authenticated page shell with header and optional sign-out.
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
          <h1 className={pageTitleClass}>{title}</h1>
          {subtitle && <p className="mt-2 text-slate-600">{subtitle}</p>}
          {userEmail && !subtitle && (
            <p className="mt-2 text-slate-600">{t('common.signedInAs', { email: userEmail })}</p>
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