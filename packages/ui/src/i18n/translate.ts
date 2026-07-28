/**
 * @file Translation helpers for nested UI message keys.
 */

import type { Messages } from './messages/en'
import { enMessages } from './messages/en'
import { frMessages } from './messages/fr'
import type { UserLocale } from '@ellr/api-client'

const MESSAGE_CATALOGS: Record<UserLocale, Messages> = {
  en: enMessages,
  fr: frMessages,
}

type MessageParams = Record<string, string | number>

/**
 * Resolves a dotted message key against a locale catalog.
 * @param locale Active locale.
 * @param key Dotted path (e.g. `common.loading`).
 * @param params Optional interpolation values.
 * @returns Translated string or the key when missing.
 */
export function translate(
  locale: UserLocale,
  key: string,
  params: MessageParams = {},
): string {
  const catalog = MESSAGE_CATALOGS[locale]
  const value = key.split('.').reduce<unknown>((current, segment) => {
    if (current && typeof current === 'object' && segment in current) {
      return (current as Record<string, unknown>)[segment]
    }
    return undefined
  }, catalog)

  if (typeof value !== 'string') {
    return key
  }

  return value.replace(/\{\{(\w+)\}\}/g, (_, token: string) => String(params[token] ?? ''))
}
