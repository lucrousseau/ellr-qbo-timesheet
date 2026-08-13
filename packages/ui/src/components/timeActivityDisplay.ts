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
export function formatEntryDateTime(
  isoValue: string | null,
  locale: UserLocale,
  timeZone?: string | null,
): string {
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
    ...(timeZone ? { timeZone } : {}),
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

/**
 * Formats a compact start/end range for stacked entry layouts.
 * @param startTime ISO start timestamp.
 * @param endTime ISO end timestamp.
 * @param locale Active UI locale.
 * @param timeZone Optional IANA timezone.
 * @param emptyLabel Fallback when either timestamp is missing.
 * @returns Localized date with a short time range.
 */
export function formatEntryDateTimeRange(
  startTime: string | null,
  endTime: string | null,
  locale: UserLocale,
  timeZone: string | null | undefined,
  emptyLabel: string,
): string {
  const start = formatEntryDateTime(startTime, locale, timeZone)
  const end = formatEntryDateTime(endTime, locale, timeZone)

  if (!start && !end) {
    return emptyLabel
  }

  if (!start || !end) {
    return start || end
  }

  return `${start} → ${end}`
}
