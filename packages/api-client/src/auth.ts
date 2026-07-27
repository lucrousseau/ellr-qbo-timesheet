import { ApiError, apiFetch } from './api'

/**
 * Authenticated user with optional QuickBooks employee mapping.
 */
export type User = {
  id: number
  name: string
  email: string
  qbo_employee_ref?: string | null
  qbo_employee_name?: string | null
}

/**
 * Opens a Sanctum session with email and password.
 * @param email Account email address.
 * @param password Account password.
 * @returns Signed-in user.
 */
export async function login(email: string, password: string): Promise<User> {
  const response = await apiFetch<{ user: User }>('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })

  return response.user
}

/**
 * Ends the Sanctum session on the server.
 * @returns Promise resolved after sign-out.
 */
export async function logout(): Promise<void> {
  await apiFetch('/logout', { method: 'POST' })
}

/**
 * Loads the current user or `null` when no session exists.
 * @returns Signed-in user, or `null` for a 401 response.
 */
export async function fetchCurrentUser(): Promise<User | null> {
  try {
    const response = await apiFetch<{ user: User }>('/user')
    return response.user
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null
    }
    throw error
  }
}

/**
 * Links a QuickBooks employee to the user account (admin).
 * @param qboEmployeeRef QuickBooks employee ID.
 * @param qboEmployeeName Optional display name.
 * @returns Updated user.
 */
export async function updateQboEmployee(
  qboEmployeeRef: string,
  qboEmployeeName?: string,
): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/qbo-employee', {
    method: 'PATCH',
    body: JSON.stringify({
      qbo_employee_ref: qboEmployeeRef,
      qbo_employee_name: qboEmployeeName ?? null,
    }),
  })

  return response.user
}
