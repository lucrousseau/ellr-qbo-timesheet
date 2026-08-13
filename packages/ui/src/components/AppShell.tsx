/**
 * @file Shared application shell with brand mark, header, and layout chrome.
 */

import type { User } from '@ellr/api-client'
import type { ReactNode } from 'react'
import { useLocale } from '../i18n/LocaleProvider'
import { pageMainClass, pageMainWideClass, pageSubtitleClass, pageTitleClass } from '../styles/tokens'
import { Button } from './Button'
import { EllrLogoMark } from './EllrLogoMark'
import { SupportContactLink } from './SupportContactLink'

type AppShellUser = Pick<User, 'name' | 'email'> & {
  first_name?: string | null
  organization?: { name: string } | null
}

type AppShellProps = {
  title: string
  subtitle?: string
  /** Signed-in user used for greeting, company, and email identity. */
  user?: AppShellUser | null
  /**
   * @deprecated Prefer `user`. Kept for callers that only know the email.
   */
  userEmail?: string | null
  onLogout?: () => void
  loggingOut?: boolean
  /** Wider main column for dense table layouts. */
  wide?: boolean
  children: ReactNode
}

/**
 * Resolves a short greeting name from the signed-in user.
 * @param user Session user with optional first name.
 * @returns First name when present, otherwise the first token of the full name.
 */
function resolveGreetingName(user: AppShellUser): string {
  const firstName = user.first_name?.trim()
  if (firstName) {
    return firstName
  }

  const fromFullName = user.name.trim().split(/\s+/)[0]
  return fromFullName || user.name
}

/**
 * Authenticated page shell with brand mark, header, and optional sign-out.
 * @param props.title Main application title.
 * @param props.subtitle Optional product context below the title (e.g. Administration).
 * @param props.user Signed-in user for greeting and company identity.
 * @param props.userEmail Legacy email-only identity when `user` is omitted.
 * @param props.onLogout Sign-out callback.
 * @param props.loggingOut Whether sign-out is in progress.
 * @param props.wide Wider main column for dense tables.
 * @param props.children Main page content.
 * @returns Centered layout with header.
 */
export function AppShell({
  title,
  subtitle,
  user,
  userEmail,
  onLogout,
  loggingOut = false,
  wide = false,
  children,
}: AppShellProps) {
  const { t } = useLocale()
  const email = user?.email ?? userEmail ?? null
  const organizationName = user?.organization?.name ?? null
  const greetingName = user ? resolveGreetingName(user) : null
  const hasSession = Boolean(user || email)
  const identityDetail = [organizationName, email].filter(Boolean).join(' · ')

  return (
    <main className={wide ? pageMainWideClass : pageMainClass}>
      <header className="mb-8 flex items-start justify-between gap-4">
        <div>
          <EllrLogoMark />
          <h1 className={`mt-6 ${pageTitleClass}`}>{title}</h1>
          {subtitle ? <p className={pageSubtitleClass}>{subtitle}</p> : null}
          {greetingName ? (
            <p className={pageSubtitleClass}>{t('common.helloUser', { name: greetingName })}</p>
          ) : null}
          {identityDetail ? (
            <p className={greetingName ? 'mt-1 text-brand-muted' : pageSubtitleClass}>
              {greetingName || organizationName
                ? identityDetail
                : t('common.signedInAs', { email: email! })}
            </p>
          ) : null}
        </div>
        <div className="flex shrink-0 flex-col items-end gap-2">
          <SupportContactLink />
          {hasSession && onLogout ? (
            <Button
              type="button"
              variant="secondary"
              size="compact"
              onClick={onLogout}
              disabled={loggingOut}
            >
              {loggingOut ? t('common.signingOut') : t('common.signOut')}
            </Button>
          ) : null}
        </div>
      </header>
      {children}
    </main>
  )
}
