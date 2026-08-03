/**
 * @file Helpers to normalize QuickBooks time activity payloads for UI display.
 */

import type { TimeActivity } from './timesheet'

/**
 * Display-ready row derived from a QuickBooks time activity entity or local time entry.
 */
export type TimeActivityRow = {
  id: string
  /** Numeric local time entry id used by approval API routes. */
  timeEntryId?: number | null
  startTime: string | null
  endTime: string | null
  durationSeconds: number
  customerName: string | null
  serviceName: string | null
  description: string | null
  ticketKey?: string | null
  ticketUrl?: string | null
  ticketTitle?: string | null
  isBillable: boolean
  billableLocked: boolean
  approvalStatus?: 'pending' | 'approved' | 'rejected' | null
  employeeName?: string | null
  rejectionReason?: string | null
  qboId?: string | null
}

/**
 * Partial update payload for an existing time activity.
 */
export type TimeActivityUpdatePayload = {
  start_time?: string
  end_time?: string
  description?: string | null
  is_billable?: boolean
}

/**
 * Reads the display name from a QuickBooks reference object.
 * @param ref QuickBooks reference field from a time activity.
 * @returns Reference name when present.
 */
function readRefName(ref: unknown): string | null {
  if (!ref || typeof ref !== 'object') {
    return null
  }

  const name = 'name' in ref ? (ref as { name?: unknown }).name : null

  return typeof name === 'string' && name.trim() !== '' ? name : null
}

/**
 * Builds the client / project label from enriched QuickBooks references.
 * @param activity QuickBooks time activity entity from the API.
 * @returns Combined client and project label when available.
 */
function formatClientProjectLabel(activity: TimeActivity): string | null {
  const customer = readRefName(activity.CustomerRef)
  const project = readRefName(activity.ProjectRef)

  if (customer && project && customer !== project) {
    return `${customer} / ${project}`
  }

  return customer ?? project
}

/**
 * Computes elapsed seconds from start/end timestamps or QBO hour fields.
 * @param startTime ISO start timestamp from QuickBooks.
 * @param endTime ISO end timestamp from QuickBooks.
 * @param activity Raw QuickBooks time activity entity.
 * @returns Duration in whole seconds.
 */
function computeDurationSeconds(
  startTime: string | null,
  endTime: string | null,
  activity: TimeActivity,
): number {
  if (startTime && endTime) {
    const startMs = new Date(startTime).getTime()
    const endMs = new Date(endTime).getTime()

    if (!Number.isNaN(startMs) && !Number.isNaN(endMs) && endMs > startMs) {
      return Math.floor((endMs - startMs) / 1000)
    }
  }

  const hours = Number(activity.Hours ?? 0)
  const minutes = Number(activity.Minutes ?? 0)

  return Math.max(0, Math.floor(hours * 3600 + minutes * 60))
}

/**
 * Maps a raw QuickBooks time activity into a stable display row.
 * @param activity QuickBooks time activity entity from the API.
 * @returns Normalized row for tables and forms.
 */
export function parseTimeActivityRow(activity: TimeActivity): TimeActivityRow {
  const startTime = typeof activity.StartTime === 'string' ? activity.StartTime : null
  const endTime = typeof activity.EndTime === 'string' ? activity.EndTime : null
  const billableStatus = typeof activity.BillableStatus === 'string' ? activity.BillableStatus : null

  return {
    id: String(activity.Id ?? ''),
    startTime,
    endTime,
    durationSeconds: computeDurationSeconds(startTime, endTime, activity),
    customerName: formatClientProjectLabel(activity),
    serviceName: readRefName(activity.ItemRef),
    description: typeof activity.Description === 'string' ? activity.Description : null,
    ticketKey: null,
    ticketUrl: null,
    ticketTitle: null,
    isBillable: billableStatus === 'Billable',
    billableLocked: billableStatus === 'HasBeenBilled',
  }
}

/**
 * Formats a duration as H:MM for spreadsheet-style tables.
 * @param totalSeconds Duration in whole seconds.
 * @returns Hours and minutes separated by a colon.
 */
export function formatTimeActivityDuration(totalSeconds: number): string {
  const safeSeconds = Math.max(0, Math.floor(totalSeconds))
  const hours = Math.floor(safeSeconds / 3600)
  const minutes = Math.floor((safeSeconds % 3600) / 60)

  return `${hours}:${String(minutes).padStart(2, '0')}`
}

/**
 * Converts an ISO timestamp into a datetime-local input value.
 * @param isoValue ISO timestamp from QuickBooks.
 * @returns Local datetime-local value or an empty string.
 */
export function toDateTimeLocalValue(isoValue: string | null): string {
  if (!isoValue) {
    return ''
  }

  const date = new Date(isoValue)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day}T${hours}:${minutes}`
}

/**
 * Converts a datetime-local input value into an ISO timestamp for the API.
 * @param value User-entered datetime-local value.
 * @returns ISO timestamp or null when invalid.
 */
export function fromDateTimeLocalValue(value: string): string | null {
  const trimmed = value.trim()

  if (trimmed === '') {
    return null
  }

  const date = new Date(trimmed)

  return Number.isNaN(date.getTime()) ? null : date.toISOString()
}
