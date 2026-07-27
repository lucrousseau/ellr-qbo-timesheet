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
