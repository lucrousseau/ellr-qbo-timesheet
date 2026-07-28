/**
 * @file Localized API error labels from stable resolution keys.
 */

import {
  normalizeUserLocale,
  resolveApiError,
  type ApiErrorMessageKey,
  type UserLocale,
} from '@ellr/api-client'
import { translatePasswordPolicyError } from './passwordPolicyMessages'
import { translate } from './translate'

/**
 * Returns a localized API error label for a stable catalog key.
 * @param locale Active user locale.
 * @param key Stable API error message key.
 * @returns Localized label.
 */
export function translateApiError(locale: UserLocale, key: ApiErrorMessageKey): string {
  return translate(normalizeUserLocale(locale), `api.errors.${key}`)
}

/**
 * Maps a network or API error to a user-facing localized message.
 * @param error Caught error (`ApiError`, `TypeError`, etc.).
 * @param fallback Default message when no known case matches.
 * @param locale Active user locale (defaults to English).
 * @returns Label safe to display in the UI.
 */
export function getApiErrorMessage(
  error: unknown,
  fallback: string,
  locale: UserLocale = 'en',
): string {
  const activeLocale = normalizeUserLocale(locale)
  const resolution = resolveApiError(error)

  if (resolution.type === 'catalog') {
    return translateApiError(activeLocale, resolution.key)
  }

  if (resolution.type === 'server_message') {
    return resolution.message
  }

  if (resolution.type === 'password_policy') {
    return translatePasswordPolicyError(activeLocale, resolution.code)
  }

  return fallback
}
