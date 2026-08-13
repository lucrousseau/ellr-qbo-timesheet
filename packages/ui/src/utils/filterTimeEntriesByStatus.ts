/**
 * @file Client-side status filter for unified time entry tables.
 */

import type { TimeActivityRow } from '@ellr/api-client'

/** Status filter values for time entry lists. */
export type TimeEntryStatusFilter =
  | 'not_approved'
  | 'all'
  | 'pending'
  | 'approved'
  | 'rejected'

/** Default filter: hide approved entries until the viewer asks for them. */
export const DEFAULT_TIME_ENTRY_STATUS_FILTER: TimeEntryStatusFilter = 'not_approved'

/** Ordered filter options shown in the status select. */
export const TIME_ENTRY_STATUS_FILTERS: readonly TimeEntryStatusFilter[] = [
  'not_approved',
  'all',
  'pending',
  'approved',
  'rejected',
] as const

/**
 * Filters time entry rows by approval status.
 * @param entries Unified time entry rows.
 * @param filter Selected status filter.
 * @returns Entries matching the filter.
 */
export function filterTimeEntriesByStatus(
  entries: TimeActivityRow[],
  filter: TimeEntryStatusFilter,
): TimeActivityRow[] {
  if (filter === 'all') {
    return entries
  }

  if (filter === 'not_approved') {
    return entries.filter((entry) => entry.approvalStatus !== 'approved')
  }

  return entries.filter((entry) => entry.approvalStatus === filter)
}
