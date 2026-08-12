/**
 * @file Locale-aware support mailbox helpers for in-app contact links.
 */

import type { UserLocale } from '@ellr/api-client'

/** English support inbox. */
export const SUPPORT_EMAIL_EN = 'hello@ellr.ca'

/** French support inbox. */
export const SUPPORT_EMAIL_FR = 'bonjour@ellr.ca'

/**
 * Returns the public support email for the active UI locale.
 * @param locale Active UI locale.
 * @returns Support mailbox address.
 */
export function supportEmailForLocale(locale: UserLocale): string {
  return locale === 'fr' ? SUPPORT_EMAIL_FR : SUPPORT_EMAIL_EN
}

/**
 * Builds a mailto URL for Ellr Timesheet support.
 * @param locale Active UI locale.
 * @returns Encoded mailto href with a default subject.
 */
export function supportMailtoHref(locale: UserLocale): string {
  const email = supportEmailForLocale(locale)
  const subject = encodeURIComponent('Ellr Timesheet support')
  return `mailto:${email}?subject=${subject}`
}
