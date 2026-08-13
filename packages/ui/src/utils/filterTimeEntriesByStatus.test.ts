/**
 * @file Tests for unified time entry status filtering.
 */

import { describe, expect, it } from 'vitest'
import type { TimeActivityRow } from '@ellr/api-client'
import {
  DEFAULT_TIME_ENTRY_STATUS_FILTER,
  filterTimeEntriesByStatus,
} from './filterTimeEntriesByStatus'

/**
 * Builds a minimal time entry row for filter assertions.
 * @param approvalStatus Entry approval status.
 * @param id Row identifier.
 * @returns Time activity row fixture.
 */
function entry(approvalStatus: TimeActivityRow['approvalStatus'], id: string): TimeActivityRow {
  return {
    id,
    startTime: '2026-07-29T09:00:00',
    endTime: '2026-07-29T10:00:00',
    durationSeconds: 3600,
    customerName: 'Acme',
    serviceName: 'Design',
    description: null,
    isBillable: false,
    billableLocked: false,
    approvalStatus,
  }
}

describe('filterTimeEntriesByStatus', () => {
  const entries = [
    entry('draft', '1'),
    entry('pending', '2'),
    entry('approved', '3'),
    entry('rejected', '4'),
  ]

  it('defaults to hiding approved entries', () => {
    expect(DEFAULT_TIME_ENTRY_STATUS_FILTER).toBe('not_approved')
    expect(filterTimeEntriesByStatus(entries, 'not_approved').map((row) => row.id)).toEqual([
      '1',
      '2',
      '4',
    ])
  })

  it('returns all entries or a single status', () => {
    expect(filterTimeEntriesByStatus(entries, 'all')).toHaveLength(4)
    expect(filterTimeEntriesByStatus(entries, 'approved').map((row) => row.id)).toEqual(['3'])
    expect(filterTimeEntriesByStatus(entries, 'pending').map((row) => row.id)).toEqual(['2'])
  })
})
