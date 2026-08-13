/**
 * @file Tests for manager review filters on time entry tables.
 */

import { describe, expect, it } from 'vitest'
import type { TimeActivityRow } from '@ellr/api-client'
import {
  DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
  filterTimeEntriesForReview,
  hasActiveReviewFilters,
  uniqueClientNames,
  uniqueEmployeeNames,
} from './filterTimeEntriesForReview'

/**
 * Builds a minimal time entry row for review-filter assertions.
 * @param overrides Partial row fields.
 * @returns Time activity row fixture.
 */
function entry(overrides: Partial<TimeActivityRow>): TimeActivityRow {
  return {
    id: '1',
    startTime: '2026-08-13T15:36:00Z',
    endTime: '2026-08-13T16:36:00Z',
    durationSeconds: 3600,
    customerName: "Bill's Windsurf Shop",
    serviceName: 'Design',
    description: null,
    isBillable: false,
    billableLocked: false,
    employeeName: 'Bob LeMoche',
    approvalStatus: 'pending',
    ...overrides,
  }
}

describe('filterTimeEntriesForReview', () => {
  const entries = [
    entry({ id: '1', employeeName: 'Bob LeMoche', isBillable: false }),
    entry({
      id: '2',
      employeeName: 'Jane Doe',
      customerName: 'Acme Corp',
      isBillable: true,
      startTime: '2026-08-01T10:00:00Z',
      endTime: '2026-08-01T11:00:00Z',
    }),
  ]

  it('exposes empty defaults and unique facet labels', () => {
    expect(DEFAULT_TIME_ENTRY_REVIEW_FILTERS.employee).toBe('')
    expect(uniqueEmployeeNames(entries)).toEqual(['Bob LeMoche', 'Jane Doe'])
    expect(uniqueClientNames(entries)).toEqual(['Acme Corp', "Bill's Windsurf Shop"])
  })

  it('filters by employee, client, billable, and date range', () => {
    expect(
      filterTimeEntriesForReview(entries, {
        ...DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
        employee: 'Jane Doe',
      }).map((row) => row.id),
    ).toEqual(['2'])

    expect(
      filterTimeEntriesForReview(entries, {
        ...DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
        client: "Bill's Windsurf Shop",
      }).map((row) => row.id),
    ).toEqual(['1'])

    expect(
      filterTimeEntriesForReview(entries, {
        ...DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
        billable: 'billable',
      }).map((row) => row.id),
    ).toEqual(['2'])

    expect(
      filterTimeEntriesForReview(entries, {
        ...DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
        dateFrom: '2026-08-10',
        dateTo: '2026-08-15',
      }).map((row) => row.id),
    ).toEqual(['1'])
  })

  it('detects active filters', () => {
    expect(hasActiveReviewFilters(DEFAULT_TIME_ENTRY_REVIEW_FILTERS)).toBe(false)
    expect(
      hasActiveReviewFilters({ ...DEFAULT_TIME_ENTRY_REVIEW_FILTERS, employee: 'Bob' }),
    ).toBe(true)
  })
})
