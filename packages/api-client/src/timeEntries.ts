/**
 * @file Local time entry API helpers and approval workflow types.
 */

import { apiFetch } from './api'

/** Approval lifecycle status for a local time entry. */
export type TimeEntryStatus = 'pending' | 'approved' | 'rejected'

/**
 * Local time entry returned by the API before or after supervisor review.
 */
export type TimeEntry = {
  id: number | null
  list_id: string
  user_id: number | null
  employee_name?: string | null
  customer_ref?: string | null
  customer_name?: string | null
  project_ref?: string | null
  project_name?: string | null
  item_ref?: string | null
  item_name?: string | null
  start_time: string
  end_time: string
  duration_seconds: number
  description?: string | null
  ticket_key?: string | null
  ticket_source?: 'manual' | 'jira' | 'linear' | null
  ticket_url?: string | null
  ticket_title?: string | null
  is_billable: boolean
  status: TimeEntryStatus
  reviewed_by_id?: number | null
  reviewed_by_name?: string | null
  reviewed_at?: string | null
  rejection_reason?: string | null
  qbo_id?: string | null
  created_at?: string | null
}

/**
 * Request body to create a local time entry.
 */
export type TimeEntryPayload = {
  customer_ref?: string
  project_ref?: string
  item_ref?: string
  start_time: string
  end_time: string
  description?: string | null
  ticket_key?: string | null
  ticket_source?: 'manual' | 'jira' | 'linear' | null
  ticket_url?: string | null
  ticket_title?: string | null
  is_billable?: boolean
}

/**
 * Partial update payload for a pending local time entry.
 */
export type TimeEntryUpdatePayload = Partial<TimeEntryPayload>

/**
 * Optional query parameters for listing time entries.
 */
export type ListTimeEntriesParams = {
  start_position?: number
  max_results?: number
}

/**
 * Pagination metadata for time entry list responses.
 */
export type TimeEntryListMeta = {
  count: number
  max_results: number
  start_position: number
  truncated: boolean
}

/**
 * Paginated time entry list returned by the API.
 */
export type TimeEntryListResponse = {
  data: TimeEntry[]
  meta: TimeEntryListMeta
}

/**
 * Lists local time entries for the signed-in employee.
 * @param params Optional pagination parameters.
 * @returns Entry rows and pagination metadata.
 */
export async function listTimeEntries(
  params: ListTimeEntriesParams = {},
): Promise<TimeEntryListResponse> {
  const search = new URLSearchParams()

  if (params.start_position !== undefined) {
    search.set('start_position', String(params.start_position))
  }

  if (params.max_results !== undefined) {
    search.set('max_results', String(params.max_results))
  }

  const query = search.toString()
  const path = query ? `/time-entries?${query}` : '/time-entries'

  return apiFetch<TimeEntryListResponse>(path)
}

/**
 * Creates a pending local time entry for supervisor approval.
 * @param payload Time range and optional description.
 * @returns Created local time entry.
 */
export async function createTimeEntry(payload: TimeEntryPayload): Promise<TimeEntry> {
  const response = await apiFetch<{ data: TimeEntry }>('/time-entries', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Updates a pending local time entry owned by the signed-in employee.
 * @param id Local time entry identifier.
 * @param payload Fields to update.
 * @returns Updated local time entry.
 */
export async function updateTimeEntry(
  id: number,
  payload: TimeEntryUpdatePayload,
): Promise<TimeEntry> {
  const response = await apiFetch<{ data: TimeEntry }>(`/time-entries/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Deletes a pending or rejected local time entry.
 * @param id Local time entry identifier.
 */
export async function deleteTimeEntry(id: number): Promise<void> {
  await apiFetch(`/time-entries/${id}`, { method: 'DELETE' })
}

/**
 * Lists pending time entries the signed-in reviewer may approve.
 * @param params Optional pagination parameters.
 * @param options Optional fetch options for admin vs supervisor routes.
 * @returns Pending entries and pagination metadata.
 */
export async function listPendingTimeEntryApprovals(
  params: ListTimeEntriesParams = {},
  options: { admin?: boolean } = {},
): Promise<TimeEntryListResponse> {
  const search = new URLSearchParams()

  if (params.start_position !== undefined) {
    search.set('start_position', String(params.start_position))
  }

  if (params.max_results !== undefined) {
    search.set('max_results', String(params.max_results))
  }

  const query = search.toString()
  const basePath = options.admin ? '/admin/time-entry-approvals' : '/time-entry-approvals'
  const path = query ? `${basePath}?${query}` : basePath

  return apiFetch<TimeEntryListResponse>(path)
}

/**
 * Approves a pending time entry and synchronizes it to QuickBooks.
 * @param id Local time entry identifier.
 * @param options Optional fetch options for admin vs supervisor routes.
 * @returns Approved and synced time entry.
 */
export async function approveTimeEntry(
  id: number,
  options: { admin?: boolean } = {},
): Promise<TimeEntry> {
  const basePath = options.admin ? '/admin/time-entry-approvals' : '/time-entry-approvals'
  const response = await apiFetch<{ data: TimeEntry }>(`${basePath}/${id}/approve`, {
    method: 'POST',
  })

  return response.data
}

/**
 * Rejects a pending time entry without synchronizing it to QuickBooks.
 * @param id Local time entry identifier.
 * @param reason Optional rejection reason shown to the employee.
 * @param options Optional fetch options for admin vs supervisor routes.
 * @returns Rejected time entry.
 */
export async function rejectTimeEntry(
  id: number,
  reason?: string | null,
  options: { admin?: boolean } = {},
): Promise<TimeEntry> {
  const basePath = options.admin ? '/admin/time-entry-approvals' : '/time-entry-approvals'
  const response = await apiFetch<{ data: TimeEntry }>(`${basePath}/${id}/reject`, {
    method: 'POST',
    body: JSON.stringify({ reason: reason ?? null }),
  })

  return response.data
}

/**
 * Resolves the numeric local time entry id from a list row identifier.
 * @param listId Unified list id such as `local:12` or a bare numeric string.
 * @returns Local time entry primary key.
 */
export function resolveTimeEntryId(listId: string): number {
  const prefixed = listId.match(/^local:(\d+)$/)

  if (prefixed) {
    return Number(prefixed[1])
  }

  const numeric = Number(listId)

  if (Number.isInteger(numeric) && numeric > 0) {
    return numeric
  }

  throw new Error(`Invalid time entry id: ${listId}`)
}

/**
 * Assigns or clears the supervisor for a provisioned timesheet user.
 * @param userId Target timesheet user identifier.
 * @param supervisorId Supervisor user identifier or null to clear.
 * @returns Updated user payload.
 */
export async function updateAdminUserSupervisor(
  userId: number,
  supervisorId: number | null,
): Promise<{ supervisor_id: number | null }> {
  const response = await apiFetch<{ data: { supervisor_id: number | null } }>(
    `/admin/users/${userId}/supervisor`,
    {
      method: 'PATCH',
      body: JSON.stringify({ supervisor_id: supervisorId }),
    },
  )

  return { supervisor_id: response.data.supervisor_id }
}
