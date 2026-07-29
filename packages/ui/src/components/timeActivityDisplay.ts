/**
 * @file Display helpers for time activity table cells.
 */

import { formatTimeActivityDuration } from '@ellr/api-client'
import type { UserLocale } from '@ellr/api-client'

/**
 * Formats a time activity timestamp for read-only table cells.
 * @param isoValue ISO timestamp from QuickBooks.
 * @param locale Active UI locale.
 * @returns Localized date and time string.
 */
export function formatEntryDateTime(isoValue: string | null, locale: UserLocale): string {
  if (!isoValue) {
    return ''
  }

  const date = new Date(isoValue)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return new Intl.DateTimeFormat(locale === 'fr' ? 'fr-CA' : 'en-CA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

/**
 * Formats a duration for read-only table cells.
 * @param durationSeconds Duration in whole seconds.
 * @returns H:MM duration string.
 */
export function formatEntryDuration(durationSeconds: number): string {
  return formatTimeActivityDuration(durationSeconds)
}
