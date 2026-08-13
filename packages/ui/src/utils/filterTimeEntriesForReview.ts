/**
 * @file Client-side review filters for pending time entry approval tables.
 */

import type { TimeActivityRow } from '@ellr/api-client'

/** Billable facet for approval review filters. */
export type TimeEntryBillableFilter = 'all' | 'billable' | 'non_billable'

/** Client-side filter state for manager/supervisor review tables. */
export type TimeEntryReviewFilters = {
  employee: string
  client: string
  billable: TimeEntryBillableFilter
  dateFrom: string
  dateTo: string
}

/** Default review filters: show every loaded pending row. */
export const DEFAULT_TIME_ENTRY_REVIEW_FILTERS: TimeEntryReviewFilters = {
  employee: '',
  client: '',
  billable: 'all',
  dateFrom: '',
  dateTo: '',
}

/** Ordered billable filter options. */
export const TIME_ENTRY_BILLABLE_FILTERS: readonly TimeEntryBillableFilter[] = [
  'all',
  'billable',
  'non_billable',
] as const

/**
 * Collects sorted unique employee names from loaded rows.
 * @param entries Time entry rows currently in memory.
 * @returns Distinct employee display names.
 */
export function uniqueEmployeeNames(entries: TimeActivityRow[]): string[] {
  return uniqueSortedLabels(entries.map((entry) => entry.employeeName))
}

/**
 * Collects sorted unique client/project labels from loaded rows.
 * @param entries Time entry rows currently in memory.
 * @returns Distinct client/project display names.
 */
export function uniqueClientNames(entries: TimeActivityRow[]): string[] {
  return uniqueSortedLabels(entries.map((entry) => entry.customerName))
}

/**
 * Applies manager review filters to loaded time entry rows.
 * @param entries Unified time entry rows.
 * @param filters Active review filter state.
 * @returns Entries matching every selected criterion.
 */
export function filterTimeEntriesForReview(
  entries: TimeActivityRow[],
  filters: TimeEntryReviewFilters,
): TimeActivityRow[] {
  return entries.filter((entry) => matchesReviewFilters(entry, filters))
}

/**
 * Returns whether any review filter is active.
 * @param filters Review filter state.
 * @returns True when at least one facet narrows the list.
 */
export function hasActiveReviewFilters(filters: TimeEntryReviewFilters): boolean {
  return (
    filters.employee !== '' ||
    filters.client !== '' ||
    filters.billable !== 'all' ||
    filters.dateFrom !== '' ||
    filters.dateTo !== ''
  )
}

/**
 * Deduplicates and sorts non-empty labels.
 * @param values Raw label values.
 * @returns Sorted unique labels.
 */
function uniqueSortedLabels(values: Array<string | null | undefined>): string[] {
  const labels = new Set<string>()

  for (const value of values) {
    const trimmed = value?.trim()
    if (trimmed) {
      labels.add(trimmed)
    }
  }

  return [...labels].sort((left, right) => left.localeCompare(right))
}

/**
 * Tests one row against the active review filters.
 * @param entry Candidate row.
 * @param filters Active filters.
 * @returns True when the row should remain visible.
 */
function matchesReviewFilters(
  entry: TimeActivityRow,
  filters: TimeEntryReviewFilters,
): boolean {
  if (filters.employee !== '' && (entry.employeeName?.trim() ?? '') !== filters.employee) {
    return false
  }

  if (filters.client !== '' && (entry.customerName?.trim() ?? '') !== filters.client) {
    return false
  }

  if (filters.billable === 'billable' && !entry.isBillable) {
    return false
  }

  if (filters.billable === 'non_billable' && entry.isBillable) {
    return false
  }

  const startMs = Date.parse(entry.startTime ?? '')
  if (Number.isNaN(startMs)) {
    return filters.dateFrom === '' && filters.dateTo === ''
  }

  if (filters.dateFrom !== '') {
    const fromMs = Date.parse(`${filters.dateFrom}T00:00:00`)
    if (!Number.isNaN(fromMs) && startMs < fromMs) {
      return false
    }
  }

  if (filters.dateTo !== '') {
    const toMs = Date.parse(`${filters.dateTo}T23:59:59.999`)
    if (!Number.isNaN(toMs) && startMs > toMs) {
      return false
    }
  }

  return true
}
