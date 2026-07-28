/**
 * @file Supported user locale codes shared with the Laravel API.
 */

/** Locales available for user-facing copy. */
export type UserLocale = 'en' | 'fr'

/** Ordered list of supported locales. */
export const SUPPORTED_LOCALES: readonly UserLocale[] = ['en', 'fr']

/**
 * Normalizes an API or user value to a supported locale.
 * @param value Raw locale from the API or UI.
 * @returns Supported locale, defaulting to English.
 */
export function normalizeUserLocale(value: string | null | undefined): UserLocale {
  return value === 'fr' ? 'fr' : 'en'
}
