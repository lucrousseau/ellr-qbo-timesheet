/**
 * @file Tests for timezone preference helpers.
 */

import { describe, expect, it } from 'vitest'
import {
  normalizeUserTimezone,
  timezonePreferenceForApi,
  timezonePreferenceOptions,
} from './timezone'

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
})
