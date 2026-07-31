/**
 * @file Helpers to normalize local time entry payloads for UI display.
 */

import type { TimeEntry } from './timeEntries'
import type { TimeActivityRow } from './timeActivityFormat'

/**
 * Maps a local time entry API payload into a table row with approval status.
 * @param entry Local time entry from the API.
 * @returns Normalized row for shared time activity tables.
 */
export function parseTimeEntryRow(entry: TimeEntry): TimeActivityRow {
  const customerLabel = entry.customer_name ?? entry.customer_ref ?? null
  const projectLabel = entry.project_name ?? entry.project_ref ?? null
  const customerName =
    projectLabel && customerLabel && projectLabel !== customerLabel
      ? `${customerLabel} / ${projectLabel}`
      : customerLabel ?? projectLabel

  return {
    id: entry.list_id ?? String(entry.id ?? entry.qbo_id ?? ''),
    timeEntryId: entry.id,
    startTime: entry.start_time,
    endTime: entry.end_time,
    durationSeconds: entry.duration_seconds,
    customerName,
    serviceName: entry.item_name ?? entry.item_ref ?? null,
    description: entry.description ?? null,
    isBillable: entry.is_billable,
    billableLocked: false,
    approvalStatus: entry.status,
    employeeName: entry.employee_name ?? null,
    rejectionReason: entry.rejection_reason ?? null,
    qboId: entry.qbo_id ?? null,
  }
}
