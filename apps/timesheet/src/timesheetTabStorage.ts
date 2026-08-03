/**
 * @file Session storage helpers for the timesheet dashboard active tab.
 */

export const TIMESHEET_ACTIVE_TAB_STORAGE_PREFIX = 'ellr.timesheet.activeTab'

/** Persisted timesheet dashboard tab ids. */
export type TimesheetTab = 'timer' | 'expenses' | 'approvals' | 'preferences'

/** Known timesheet dashboard tab ids. */
const TIMESHEET_TABS: readonly TimesheetTab[] = ['timer', 'expenses', 'approvals', 'preferences']

/**
 * Builds a per-user sessionStorage key for the active timesheet tab.
 * @param userId Authenticated user id.
 * @returns Namespaced storage key.
 */
export function timesheetActiveTabStorageKey(userId: number): string {
  return `${TIMESHEET_ACTIVE_TAB_STORAGE_PREFIX}:${userId}`
}

/**
 * Type guard for persisted timesheet tab ids.
 * @param value Raw sessionStorage value.
 * @returns Whether the value is a known timesheet tab id.
 */
export function isTimesheetTab(value: string): value is TimesheetTab {
  return (TIMESHEET_TABS as readonly string[]).includes(value)
}
