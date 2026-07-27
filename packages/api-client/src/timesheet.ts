import { apiFetch } from './api'

export type TimeActivityPayload = {
  customer_ref?: string
  customer_name?: string
  start_time: string
  end_time: string
  description?: string | null
}

export type TimeActivity = {
  Id?: string
  [key: string]: unknown
}

export async function createTimeActivity(payload: TimeActivityPayload): Promise<TimeActivity> {
  const response = await apiFetch<{ data: TimeActivity }>('/time-activities', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.data
}
