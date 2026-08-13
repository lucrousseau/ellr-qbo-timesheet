/**
 * @file Tests for time activity display helpers.
 */

import { describe, expect, it } from 'vitest'
import { formatEntryDateTimeRange, formatEntryDuration } from './timeActivityDisplay'

describe('timeActivityDisplay', () => {
  it('formats durations as H:MM', () => {
    expect(formatEntryDuration(7200)).toBe('2:00')
  })

  it('formats a start and end range', () => {
    expect(
      formatEntryDateTimeRange(
        '2026-07-29T09:00:00Z',
        '2026-07-29T11:00:00Z',
        'en',
        'UTC',
        '-',
      ),
    ).toContain('→')
  })

  it('falls back when timestamps are missing', () => {
    expect(formatEntryDateTimeRange(null, null, 'en', 'UTC', '-')).toBe('-')
    expect(formatEntryDateTimeRange('2026-07-29T09:00:00Z', null, 'en', 'UTC', '-')).not.toBe('-')
  })
})
