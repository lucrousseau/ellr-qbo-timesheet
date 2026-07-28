/**
 * @file Vitest helpers that wrap hooks and components with LocaleProvider.
 */

import type { ReactNode } from 'react'
import { renderHook, type RenderHookResult } from '@testing-library/react'
import type { UserLocale } from '@ellr/api-client'
import { LocaleProvider } from '../i18n/LocaleProvider'

/**
 * Builds a wrapper component that supplies locale context to children.
 * @param locale Initial locale for the provider.
 * @returns React wrapper for testing-library.
 */
export function createLocaleWrapper(locale: UserLocale = 'en') {
  return function LocaleWrapper({ children }: { children: ReactNode }) {
    return <LocaleProvider initialLocale={locale}>{children}</LocaleProvider>
  }
}

/**
 * Renders a hook inside LocaleProvider for i18n-aware hook tests.
 * @param hook Hook factory to render.
 * @param locale Initial locale for the provider.
 * @returns Testing-library hook render result.
 */
export function renderHookWithLocale<Result, Props>(
  hook: (props: Props) => Result,
  locale: UserLocale = 'en',
): RenderHookResult<Result, Props> {
  return renderHook(hook, { wrapper: createLocaleWrapper(locale) })
}
