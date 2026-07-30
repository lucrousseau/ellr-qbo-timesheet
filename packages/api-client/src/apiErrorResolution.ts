/**
 * @file Maps API and network errors to stable resolution keys (no UI copy).
 */

import { passwordPolicyErrorCodeFromApiMessage } from '@ellr/password-policy'
import type { PasswordPolicyErrorCode } from '@ellr/password-policy'
import { ApiError } from './api'

/** Ordered catalog keys for known API business errors. */
export const API_ERROR_MESSAGE_KEYS = [
  'session_expired',
  'quickbooks_not_connected',
  'quickbooks_expired',
  'registration_disabled',
  'qbo_employee_not_configured',
  'admin_required',
  'organization_required',
  'email_not_verified',
  'access_denied',
  'qbo_employee_invalid',
  'qbo_employee_removed',
  'qbo_employee_email_missing',
  'qbo_employee_email_conflict',
  'qbo_insufficient_permissions',
  'invalid_data',
  'quickbooks_busy',
  'network_unreachable',
] as const

/** Stable catalog keys for known API business errors. */
export type ApiErrorMessageKey = (typeof API_ERROR_MESSAGE_KEYS)[number]

/**
 * Detects fetch and network failures without treating every TypeError as an outage.
 * @param error Browser or runtime TypeError from a failed HTTP call.
 * @returns `true` when the message matches a known network failure pattern.
 */
function isLikelyNetworkTypeError(error: TypeError): boolean {
  const message = error.message.toLowerCase()

  return (
    message.includes('failed to fetch') ||
    message.includes('networkerror') ||
    message.includes('network request failed') ||
    message.includes('load failed')
  )
}

/** Result of resolving a caught error before UI translation. */
export type ApiErrorResolution =
  | { type: 'catalog'; key: ApiErrorMessageKey }
  | { type: 'server_message'; message: string }
  | { type: 'password_policy'; code: PasswordPolicyErrorCode }
  | { type: 'fallback' }

/**
 * Resolves a network or API error to a catalog key, server message, or fallback.
 * @param error Caught error (`ApiError`, `TypeError`, etc.).
 * @returns Resolution consumed by `getApiErrorMessage` in `@ellr/ui`.
 */
export function resolveApiError(error: unknown): ApiErrorResolution {
  if (error instanceof ApiError) {
    if (error.status === 401) {
      if (error.code === 'invalid_credentials') {
        return { type: 'server_message', message: error.message }
      }

      return { type: 'catalog', key: 'session_expired' }
    }

    if (error.status === 419) {
      return { type: 'catalog', key: 'session_expired' }
    }

    if (error.status === 403) {
      if (error.code === 'quickbooks_not_connected') {
        return { type: 'catalog', key: 'quickbooks_not_connected' }
      }

      if (error.code === 'quickbooks_expired') {
        return { type: 'catalog', key: 'quickbooks_expired' }
      }

      if (error.code === 'registration_disabled') {
        return { type: 'catalog', key: 'registration_disabled' }
      }

      if (error.code === 'qbo_employee_not_configured') {
        return { type: 'catalog', key: 'qbo_employee_not_configured' }
      }

      if (error.code === 'admin_required') {
        return { type: 'catalog', key: 'admin_required' }
      }

      if (error.code === 'organization_required') {
        return { type: 'catalog', key: 'organization_required' }
      }

      if (error.code === 'email_not_verified') {
        return { type: 'catalog', key: 'email_not_verified' }
      }

      if (error.code === 'qbo_employee_removed') {
        return { type: 'catalog', key: 'qbo_employee_removed' }
      }

      if (error.code === 'qbo_employee_email_missing') {
        return { type: 'catalog', key: 'qbo_employee_email_missing' }
      }

      if (error.code === 'qbo_employee_email_conflict') {
        return { type: 'catalog', key: 'qbo_employee_email_conflict' }
      }

      if (error.code === 'qbo_insufficient_permissions') {
        return { type: 'catalog', key: 'qbo_insufficient_permissions' }
      }

      return { type: 'catalog', key: 'access_denied' }
    }

    if (error.status === 422) {
      if (error.code === 'qbo_employee_invalid') {
        return { type: 'catalog', key: 'qbo_employee_invalid' }
      }

      if (error.code === 'qbo_employee_email_missing') {
        return { type: 'catalog', key: 'qbo_employee_email_missing' }
      }

      if (error.code === 'qbo_employee_email_conflict') {
        return { type: 'catalog', key: 'qbo_employee_email_conflict' }
      }

      const passwordPolicyCode = passwordPolicyErrorCodeFromApiMessage(error.message)
      if (passwordPolicyCode !== null) {
        return { type: 'password_policy', code: passwordPolicyCode }
      }

      if (error.message && !error.message.startsWith('API error:')) {
        return { type: 'server_message', message: error.message }
      }

      return { type: 'catalog', key: 'invalid_data' }
    }

    if (error.status === 503) {
      return { type: 'catalog', key: 'quickbooks_busy' }
    }
  }

  if (error instanceof TypeError && isLikelyNetworkTypeError(error)) {
    return { type: 'catalog', key: 'network_unreachable' }
  }

  return { type: 'fallback' }
}
