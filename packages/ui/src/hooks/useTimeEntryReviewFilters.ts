/**
 * @file Local review-filter state for pending time entry approval tables.
 */

import { useMemo, useState } from 'react'
import type { TimeActivityRow } from '@ellr/api-client'
import {
  DEFAULT_TIME_ENTRY_REVIEW_FILTERS,
  filterTimeEntriesForReview,
  hasActiveReviewFilters,
  uniqueClientNames,
  uniqueEmployeeNames,
  type TimeEntryReviewFilters,
} from '../utils/filterTimeEntriesForReview'

/**
 * Owns client-side review filters and derived facet options for loaded rows.
 * @param entries Pending entries currently loaded from the API.
 * @returns Filter state, setters, and filtered rows.
 */
export function useTimeEntryReviewFilters(entries: TimeActivityRow[]) {
  const [filters, setFilters] = useState<TimeEntryReviewFilters>(DEFAULT_TIME_ENTRY_REVIEW_FILTERS)
  const filteredEntries = useMemo(
    () => filterTimeEntriesForReview(entries, filters),
    [entries, filters],
  )
  const employeeOptions = useMemo(() => uniqueEmployeeNames(entries), [entries])
  const clientOptions = useMemo(() => uniqueClientNames(entries), [entries])

  return {
    filters,
    setFilters,
    filteredEntries,
    employeeOptions,
    clientOptions,
    filtersActive: hasActiveReviewFilters(filters),
  }
}
