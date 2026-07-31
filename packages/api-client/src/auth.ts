/**
 * @file Authentication helpers: login, logout, session user, and QBO employee mapping.
 */

import { ApiError, apiFetch } from './api'
import type { UserLocale } from './locale'
import type { UserLevel } from './userLevel'

/**
 * Tenant organization linked to the signed-in user.
 */
export type Organization = {
  id: number
  name: string
  slug: string
  qbo_connected: boolean
  company_timezone?: string | null
}

/**
 * Authenticated user with optional QuickBooks employee mapping.
 */
export type User = {
  id: number
  name: string
  email: string
  email_verified_at?: string | null
  locale?: UserLocale | null
  timezone?: string | null
  effective_display_timezone?: string | null
  level?: UserLevel | null
  is_admin?: boolean
  is_super_admin?: boolean
  organization?: Organization | null
  qbo_employee_ref?: string | null
  assigned_customers?: Array<{ id: string; display_name: string }>
  all_customers_access?: boolean
  supervisor_id?: number | null
  can_review_time_entries?: boolean
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
 * Payload for public self-service registration.
 */
export type RegisterPayload = {
  name: string
  email: string
  password: string
  password_confirmation: string
  organization_name?: string | null
}

/**
 * Creates a user account and opens a Sanctum session when registration is enabled.
 * @param payload Registration fields including optional organization name.
 * @returns Newly registered user.
 */
export async function register(payload: RegisterPayload): Promise<User> {
  const response = await apiFetch<{ user: User }>('/register', {
    method: 'POST',
    body: JSON.stringify(payload),
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
 * @returns Updated user.
 */
export async function updateQboEmployee(qboEmployeeRef: string): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/qbo-employee', {
    method: 'PATCH',
    body: JSON.stringify({
      qbo_employee_ref: qboEmployeeRef,
    }),
  })

  return response.user
}

/**
 * Updates the signed-in user locale preference.
 * @param locale Supported locale code.
 * @returns Updated user.
 */
export async function updateUserLocale(locale: UserLocale): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/locale', {
    method: 'PATCH',
    body: JSON.stringify({ locale }),
  })

  return response.user
}

/**
 * Updates the signed-in user timezone preference.
 * @param timezone IANA timezone identifier, or null to follow the company timezone.
 * @returns Updated user.
 */
export async function updateUserTimezone(timezone: string | null): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/timezone', {
    method: 'PATCH',
    body: JSON.stringify({ timezone }),
  })

  return response.user
}

/**
 * Updates locale and timezone preferences in a single request.
 * @param preferences Locale and optional timezone values.
 * @returns Updated user.
 */
export async function updateUserPreferences(preferences: {
  locale: UserLocale
  timezone: string | null
}): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/preferences', {
    method: 'PATCH',
    body: JSON.stringify(preferences),
  })

  return response.user
}

/**
 * Changes the signed-in user password after validating the current password.
 * @param currentPassword Existing account password.
 * @param password New password value.
 * @param passwordConfirmation Confirmation of the new password.
 * @returns Promise resolved after the password is updated.
 */
export async function changePassword(
  currentPassword: string,
  password: string,
  passwordConfirmation: string,
): Promise<void> {
  await apiFetch('/user/password', {
    method: 'PATCH',
    body: JSON.stringify({
      current_password: currentPassword,
      password,
      password_confirmation: passwordConfirmation,
    }),
  })
}

/**
 * Links a QuickBooks employee to another user account (administrator only).
 * @param userId Target application user ID.
 * @param qboEmployeeRef QuickBooks employee ID.
 * @returns Updated user.
 */
export async function updateUserQboEmployee(
  userId: number,
  qboEmployeeRef: string,
): Promise<User> {
  const response = await apiFetch<{ user: User }>(`/admin/users/${userId}/qbo-employee`, {
    method: 'PATCH',
    body: JSON.stringify({
      qbo_employee_ref: qboEmployeeRef,
    }),
  })

  return response.user
}

/**
 * Sends a password reset link to the given email address.
 * @param email Account email address.
 * @param options Optional client hint so the email deep link opens the right app.
 * @returns Promise resolved after the API accepts the request.
 */
export async function requestPasswordReset(
  email: string,
  options?: { client?: 'admin' | 'timesheet' },
): Promise<void> {
  await apiFetch('/forgot-password', {
    method: 'POST',
    body: JSON.stringify({
      email,
      ...(options?.client ? { client: options.client } : {}),
    }),
  })
}

/**
 * Resets the account password using a token from email.
 * @param payload Reset token, email, and new password fields.
 * @returns Promise resolved after the password is updated.
 */
export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  passwordConfirmation: string
}): Promise<void> {
  await apiFetch('/reset-password', {
    method: 'POST',
    body: JSON.stringify({
      token: payload.token,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.passwordConfirmation,
    }),
  })
}

/**
 * Queues a new email verification link for the signed-in user.
 * @returns Promise resolved after the API accepts the request.
 */
export async function resendVerificationEmail(): Promise<void> {
  await apiFetch('/email/verification-notification', { method: 'POST' })
}
