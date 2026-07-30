/**
 * @file Tests for timezone preference helpers.
 */

import { describe, expect, it } from 'vitest'
import {
  formatTimezoneLabel,
  normalizeUserTimezone,
  resolveEffectiveTimezone,
  timezonePreferenceForApi,
  timezonePreferenceOptions,
} from './timezone'
import type { User } from './auth'

describe('timezone preferences', () => {
  it('pre-fills null user timezone with the company timezone', () => {
    expect(normalizeUserTimezone(null, 'America/Toronto')).toBe('America/Toronto')
    expect(normalizeUserTimezone(undefined, 'America/Toronto')).toBe('America/Toronto')
    expect(normalizeUserTimezone('   ', 'America/Toronto')).toBe('America/Toronto')
  })

  it('keeps a personal timezone override when set', () => {
    expect(normalizeUserTimezone('Europe/Paris', 'America/Toronto')).toBe('Europe/Paris')
  })

  it('stores null when the selected timezone matches the company timezone', () => {
    expect(timezonePreferenceForApi('America/Toronto', 'America/Toronto')).toBeNull()
    expect(timezonePreferenceForApi('Europe/Paris', 'America/Toronto')).toBe('Europe/Paris')
  })

  it('includes the company timezone in picker options', () => {
    expect(timezonePreferenceOptions('America/Chicago')).toContain('America/Chicago')
  })

  it('resolves the effective display timezone from user fields', () => {
    expect(resolveEffectiveTimezone(null)).toBe('UTC')

    const user: User = {
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      effective_display_timezone: 'America/Toronto',
    }

    expect(resolveEffectiveTimezone(user)).toBe('America/Toronto')
    expect(
      resolveEffectiveTimezone({
        ...user,
        effective_display_timezone: undefined,
        timezone: 'Europe/Paris',
      }),
    ).toBe('Europe/Paris')
    expect(
      resolveEffectiveTimezone({
        ...user,
        effective_display_timezone: undefined,
        timezone: null,
        organization: {
          id: 1,
          name: 'Acme',
          slug: 'acme',
          qbo_connected: true,
          company_timezone: 'America/Chicago',
        },
      }),
    ).toBe('America/Chicago')
  })

  it('formats timezone labels for preference pickers', () => {
    expect(formatTimezoneLabel('UTC')).toContain('UTC')
    expect(formatTimezoneLabel('America/Toronto', 'fr')).toContain('America/Toronto')
    expect(formatTimezoneLabel('Invalid/Zone')).toBe('Invalid/Zone')
  })
})
