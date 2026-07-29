/**
 * @file Administrator endpoints for timesheet user provisioning.
 */

import { apiFetch } from './api'
import type { User } from './auth'

/**
 * QuickBooks customer option for administrator and timesheet pickers.
 */
export type QboCustomerOption = {
  id: string
  display_name: string
}

/**
 * Customer access settings for a provisioned timesheet user.
 */
export type TimesheetUserCustomerAccess = {
  all_customers_access: boolean
  data: QboCustomerOption[]
}

/**
 * QuickBooks employee option for administrator pickers.
 */
export type QboEmployeeOption = {
  id: string
  display_name: string
  email: string | null
}

/**
 * Payload for creating a timesheet user linked to a QuickBooks employee.
 */
export type CreateTimesheetUserPayload = {
  qbo_employee_ref: string
}

/**
 * Lists active QuickBooks employees for the connected company.
 * @param options Optional fetch options (`refresh` bypasses server cache).
 * @returns Employee id, display name, and optional email.
 */
export async function fetchQboEmployees(options?: {
  refresh?: boolean
  signal?: AbortSignal
}): Promise<QboEmployeeOption[]> {
  const query = options?.refresh ? '?refresh=1' : ''
  const response = await apiFetch<{ data: QboEmployeeOption[] }>(`/quickbooks/employees${query}`, {
    signal: options?.signal,
  })

  return response.data
}

/**
 * Lists timesheet users provisioned by administrators.
 * @returns Users with QuickBooks employee mappings.
 */
export async function fetchTimesheetUsers(): Promise<User[]> {
  const response = await apiFetch<{ data: User[] }>('/admin/users')

  return response.data
}

/**
 * Creates a timesheet user for a QuickBooks employee and sends a password-set email.
 * @param payload Employee mapping and account details.
 * @returns Created user record.
 */
export async function createTimesheetUser(payload: CreateTimesheetUserPayload): Promise<User> {
  const response = await apiFetch<{ user: User }>('/admin/users', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.user
}

/**
 * Removes a provisioned timesheet user account without deleting QuickBooks time entries.
 * @param userId Application user id to revoke.
 */
export async function deleteTimesheetUser(userId: number): Promise<void> {
  await apiFetch(`/admin/users/${userId}`, {
    method: 'DELETE',
  })
}

/**
 * Lists all active QuickBooks customers for administrator assignment pickers.
 * @param options Optional fetch options (`refresh` bypasses server cache).
 * @returns Customer id and display name rows.
 */
export async function fetchAdminQboCustomers(options?: {
  refresh?: boolean
  signal?: AbortSignal
}): Promise<QboCustomerOption[]> {
  const query = options?.refresh ? '?refresh=1' : ''
  const response = await apiFetch<{ data: QboCustomerOption[] }>(`/admin/quickbooks/customers${query}`, {
    signal: options?.signal,
  })

  return response.data
}

/**
 * Loads customer access settings for a provisioned timesheet user.
 * @param userId Application user id.
 * @returns All-clients flag and assigned QuickBooks customer rows.
 */
export async function fetchTimesheetUserCustomers(userId: number): Promise<TimesheetUserCustomerAccess> {
  return apiFetch<TimesheetUserCustomerAccess>(`/admin/users/${userId}/customers`)
}

/**
 * Replaces customer access for a provisioned timesheet user.
 * @param userId Application user id.
 * @param payload All-clients flag and optional customer identifiers.
 * @returns Updated customer access settings.
 */
export async function syncTimesheetUserCustomers(
  userId: number,
  payload: {
    all_customers_access: boolean
    customer_refs: string[]
  },
): Promise<TimesheetUserCustomerAccess> {
  return apiFetch<TimesheetUserCustomerAccess>(`/admin/users/${userId}/customers`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}
