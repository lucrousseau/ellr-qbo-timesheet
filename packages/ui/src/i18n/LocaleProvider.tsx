/**
 * @file React context for the active UI locale and translator.
 */

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { normalizeUserLocale, type UserLocale } from '@ellr/api-client'
import { translate } from './translate'

type LocaleContextValue = {
  locale: UserLocale
  setLocale: (locale: UserLocale) => void
  t: (key: string, params?: Record<string, string | number>) => string
}

const LocaleContext = createContext<LocaleContextValue | null>(null)

type LocaleProviderProps = {
  children: ReactNode
  initialLocale?: UserLocale
}

/**
 * Provides locale state and the `t` translator to descendant components.
 * @param props.children App tree.
 * @param props.initialLocale Starting locale before user bootstrap.
 * @returns Provider wrapping children.
 */
export function LocaleProvider({ children, initialLocale = 'en' }: LocaleProviderProps) {
  const [locale, setLocaleState] = useState<UserLocale>(normalizeUserLocale(initialLocale))

  const setLocale = useCallback((nextLocale: UserLocale) => {
    setLocaleState(normalizeUserLocale(nextLocale))
  }, [])

  const t = useCallback(
    (key: string, params?: Record<string, string | number>) => translate(locale, key, params),
    [locale],
  )

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t,
    }),
    [locale, setLocale, t],
  )

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>
}

/**
 * Reads the active locale and translator from context.
 * @returns Locale state and `t` helper.
 */
export function useLocale(): LocaleContextValue {
  const context = useContext(LocaleContext)

  if (context === null) {
    throw new Error('useLocale must be used within a LocaleProvider')
  }

  return context
}
