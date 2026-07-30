/**
 * @file Tests for time entry id resolution helpers.
 */

import { describe, expect, it } from 'vitest'
import { resolveTimeEntryId } from './timeEntries'

describe('resolveTimeEntryId', () => {
  it('parses prefixed local list ids', () => {
    expect(resolveTimeEntryId('local:12')).toBe(12)
  })

  it('parses bare numeric ids', () => {
    expect(resolveTimeEntryId('12')).toBe(12)
  })

  it('rejects invalid ids', () => {
    expect(() => resolveTimeEntryId('local:abc')).toThrow(/Invalid time entry id/)
  })
})
