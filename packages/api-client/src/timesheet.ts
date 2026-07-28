/**
 * @file Time activity API helpers for the timesheet app.
 */

import { apiFetch } from './api'

/**
 * Request body to create a time activity in QuickBooks.
 */
export type TimeActivityPayload = {
  customer_ref?: string
  customer_name?: string
  project_ref?: string
  project_name?: string
  item_ref?: string
  item_name?: string
  start_time: string
  end_time: string
  description?: string | null
}

/**
 * Time activity returned by the API after QBO creation.
 */
export type TimeActivity = {
  Id?: string
  [key: string]: unknown
}

/**
 * Optional query parameters for listing time activities.
 */
export type ListTimeActivitiesParams = {
  start_position?: number
  max_results?: number
}

/**
 * Pagination metadata for time activity list responses.
 */
export type TimeActivityListMeta = {
  count: number
  max_results: number
  start_position: number
  truncated: boolean
}

/**
 * Paginated time activity list returned by the API.
 */
export type TimeActivityListResponse = {
  data: TimeActivity[]
  meta: TimeActivityListMeta
}

/**
 * Lists time activities for the signed-in user's QBO employee.
 * @param params Optional QuickBooks pagination parameters.
 * @returns Activity rows and pagination metadata.
 */
export async function listTimeActivities(
  params: ListTimeActivitiesParams = {},
): Promise<TimeActivityListResponse> {
  const search = new URLSearchParams()

  if (params.start_position !== undefined) {
    search.set('start_position', String(params.start_position))
  }

  if (params.max_results !== undefined) {
    search.set('max_results', String(params.max_results))
  }

  const query = search.toString()
  const path = query ? `/time-activities?${query}` : '/time-activities'

  return apiFetch<TimeActivityListResponse>(path)
}

/**
 * Creates a time activity for the signed-in user's QBO employee.
 * @param payload Time range and optional description.
 * @returns Created QuickBooks activity data.
 */
export async function createTimeActivity(payload: TimeActivityPayload): Promise<TimeActivity> {
  const response = await apiFetch<{ data: TimeActivity }>('/time-activities', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * QuickBooks picker option for customers, projects, or services.
 */
export type QboPickerOption = {
  id: string
  display_name: string
}

/**
 * Active timer session persisted on the server.
 */
export type TimeTrackerSession = {
  customer_ref: string | null
  customer_name: string | null
  project_ref: string | null
  project_name: string | null
  service_ref: string | null
  service_name: string | null
  description: string | null
  accumulated_seconds: number
  running_since: string | null
  elapsed_seconds: number
  is_running: boolean
}

/**
 * Payload for upserting active timer state.
 */
export type TimeTrackerPayload = {
  customer_ref?: string | null
  customer_name?: string | null
  project_ref?: string | null
  project_name?: string | null
  service_ref?: string | null
  service_name?: string | null
  description?: string | null
  is_running: boolean
}

/**
 * Lists active QuickBooks customers for timesheet pickers.
 * @param options Optional fetch options (`refresh` bypasses server cache).
 * @returns Customer id and display name rows.
 */
export async function fetchQboCustomers(options?: {
  refresh?: boolean
  signal?: AbortSignal
}): Promise<QboPickerOption[]> {
  const query = options?.refresh ? '?refresh=1' : ''
  const response = await apiFetch<{ data: QboPickerOption[] }>(`/quickbooks/customers${query}`, {
    signal: options?.signal,
  })

  return response.data
}

/**
 * Lists active QuickBooks projects for a parent customer.
 * @param customerRef Parent customer QuickBooks identifier.
 * @param options Optional fetch options (`refresh` bypasses server cache).
 * @returns Project id and display name rows.
 */
export async function fetchQboProjects(
  customerRef: string,
  options?: { refresh?: boolean; signal?: AbortSignal },
): Promise<QboPickerOption[]> {
  const search = new URLSearchParams({ customer_ref: customerRef })
  if (options?.refresh) {
    search.set('refresh', '1')
  }

  const response = await apiFetch<{ data: QboPickerOption[] }>(
    `/quickbooks/projects?${search.toString()}`,
    { signal: options?.signal },
  )

  return response.data
}

/**
 * Lists active QuickBooks service items for timesheet pickers.
 * @param options Optional fetch options (`refresh` bypasses server cache).
 * @returns Service item id and display name rows.
 */
export async function fetchQboServices(options?: {
  refresh?: boolean
  signal?: AbortSignal
}): Promise<QboPickerOption[]> {
  const query = options?.refresh ? '?refresh=1' : ''
  const response = await apiFetch<{ data: QboPickerOption[] }>(`/quickbooks/services${query}`, {
    signal: options?.signal,
  })

  return response.data
}

/**
 * Returns the active timer session for the signed-in user.
 * @returns Session data or null when no session exists.
 */
export async function fetchTimeTracker(): Promise<TimeTrackerSession | null> {
  const response = await apiFetch<{ data: TimeTrackerSession | null }>('/time-tracker')

  return response.data
}

/**
 * Creates or updates the active timer session for the signed-in user.
 * @param payload Timer selections and running state.
 * @returns Updated session data.
 */
export async function updateTimeTracker(payload: TimeTrackerPayload): Promise<TimeTrackerSession> {
  const response = await apiFetch<{ data: TimeTrackerSession }>('/time-tracker', {
    method: 'PUT',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Logs elapsed time to QuickBooks and clears the active session.
 * @returns Created QuickBooks activity data.
 */
export async function logTimeTracker(): Promise<TimeActivity> {
  const response = await apiFetch<{ data: TimeActivity }>('/time-tracker/log', {
    method: 'POST',
  })

  return response.data
}

/**
 * Discards the active timer session without logging time.
 */
export async function discardTimeTracker(): Promise<void> {
  await apiFetch('/time-tracker', { method: 'DELETE' })
}
