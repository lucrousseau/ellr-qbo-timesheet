/**
 * @file Shared auth page chrome with ellr logo and product title.
 */

import type { ReactNode } from 'react'
import { pageMainClass, pageSubtitleClass, pageTitleClass } from '../styles/tokens'
import { EllrLogoMark } from './EllrLogoMark'

type AuthPageLayoutProps = {
  title: string
  subtitle?: string
  children: ReactNode
}

/**
 * Auth screen wrapper with brand mark above the product title.
 * @param props Product title, optional subtitle, and form card content.
 * @returns Centered auth page layout.
 */
export function AuthPageLayout({ title, subtitle, children }: AuthPageLayoutProps) {
  return (
    <main className={pageMainClass}>
      <header className="mb-8">
        <EllrLogoMark showTagline size="md" />
        <h1 className={`mt-6 ${pageTitleClass}`}>{title}</h1>
        {subtitle ? <p className={pageSubtitleClass}>{subtitle}</p> : null}
      </header>
      {children}
    </main>
  )
}
