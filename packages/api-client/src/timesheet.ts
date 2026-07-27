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
