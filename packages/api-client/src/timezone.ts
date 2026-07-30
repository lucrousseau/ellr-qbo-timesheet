/**
 * @file Timezone helpers and curated IANA identifiers for user preferences.
 */

import type { User } from './auth'

/**
 * Curated IANA timezone identifiers exposed in preference pickers.
 */
export const COMMON_TIMEZONES = [
  'America/St_Johns',
  'America/Halifax',
  'America/Toronto',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Phoenix',
  'America/Los_Angeles',
  'America/Anchorage',
  'Pacific/Honolulu',
  'America/Mexico_City',
  'America/Sao_Paulo',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Athens',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Australia/Sydney',
  'Pacific/Auckland',
  'UTC',
] as const

/**
 * Returns timezone options for the preferences picker, including the company timezone.
 * @param companyTimezone Organization timezone from QuickBooks, if known.
 * @returns Unique IANA timezone identifiers.
 */
export function timezonePreferenceOptions(companyTimezone?: string | null): string[] {
  const options = new Set<string>(COMMON_TIMEZONES)

  if (companyTimezone) {
    options.add(companyTimezone)
  }

  return [...options]
}

/**
 * Normalizes a stored user timezone for controlled form state.
 *
 * A null or blank user value means "follow the company timezone" and is shown
 * pre-filled with the organization timezone in the picker.
 *
 * @param value Stored user timezone from the API.
 * @param companyTimezone Organization timezone from QuickBooks, if known.
 * @returns IANA timezone identifier for the select control.
 */
export function normalizeUserTimezone(
  value: string | null | undefined,
  companyTimezone?: string | null,
): string {
  const normalized = value?.trim()

  if (normalized) {
    return normalized
  }

  return companyTimezone ?? 'UTC'
}

/**
 * Converts a picker value back to the API payload.
 *
 * Selecting the company timezone stores null on the user record.
 *
 * @param selectedTimezone Controlled picker value.
 * @param companyTimezone Organization timezone from QuickBooks, if known.
 * @returns Null when the user follows the company timezone.
 */
export function timezonePreferenceForApi(
  selectedTimezone: string,
  companyTimezone?: string | null,
): string | null {
  const normalized = selectedTimezone.trim()

  if (normalized === '' || normalized === companyTimezone) {
    return null
  }

  return normalized
}

/**
 * Resolves the timezone used to render timestamps for the signed-in user.
 * @param user Authenticated user, if any.
 * @returns IANA timezone identifier.
 */
export function resolveEffectiveTimezone(user: User | null | undefined): string {
  if (!user) {
    return 'UTC'
  }

  return user.effective_display_timezone
    ?? user.timezone
    ?? user.organization?.company_timezone
    ?? 'UTC'
}

/**
 * Formats a timezone option label for preference pickers.
 * @param timezone IANA timezone identifier.
 * @param locale Active UI locale.
 * @returns Human-readable label with UTC offset when available.
 */
export function formatTimezoneLabel(timezone: string, locale: 'en' | 'fr' = 'en'): string {
  const intlLocale = locale === 'fr' ? 'fr-CA' : 'en-CA'

  try {
    const parts = new Intl.DateTimeFormat(intlLocale, {
      timeZone: timezone,
      timeZoneName: 'shortOffset',
    }).formatToParts(new Date())

    const offset = parts.find((part) => part.type === 'timeZoneName')?.value ?? ''
    const readable = timezone.replaceAll('_', ' ')

    return offset ? `${readable} (${offset})` : readable
  } catch {
    return timezone.replaceAll('_', ' ')
  }
}
