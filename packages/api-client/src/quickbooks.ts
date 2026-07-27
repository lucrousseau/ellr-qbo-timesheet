import { apiFetch } from './api'

/**
 * OAuth QuickBooks connection state for the tenant.
 */
export type QuickBooksStatus = {
  connected: boolean
  realm_id?: string
  access_token_expires_at?: string | null
}

/**
 * Response when starting the QuickBooks OAuth flow.
 */
export type QuickBooksConnectResponse = {
  authorization_url: string
}

/**
 * Fetches QuickBooks connection status from the API.
 * @returns Connected flag, realm, and token expiry.
 */
export async function fetchQuickBooksStatus(): Promise<QuickBooksStatus> {
  return apiFetch<QuickBooksStatus>('/quickbooks/status')
}

/**
 * Requests a QuickBooks OAuth authorization URL.
 * @returns URL to open in order to connect the QBO account.
 */
export async function connectQuickBooks(): Promise<QuickBooksConnectResponse> {
  return apiFetch<QuickBooksConnectResponse>('/quickbooks/connect')
}

/**
 * Revokes the QuickBooks connection on the server.
 * @returns Object indicating whether QBO remains connected (`false` expected).
 */
export async function disconnectQuickBooks(): Promise<{ connected: boolean }> {
  return apiFetch<{ connected: boolean }>('/quickbooks/disconnect', { method: 'POST' })
}

/**
 * Parses admin OAuth callback query parameters.
 * @param search `location.search` string (e.g. `?quickbooks=connected`).
 * @returns Callback result and optional error reason.
 */
export function parseQuickBooksOAuthCallback(search: string): {
  result: 'connected' | 'error' | null
  reason?: string | null
} {
  const params = new URLSearchParams(search)
  const quickbooksResult = params.get('quickbooks')

  if (quickbooksResult === 'connected') {
    return { result: 'connected' }
  }

  if (quickbooksResult === 'error') {
    return { result: 'error', reason: params.get('reason') }
  }

  return { result: null }
}

/**
 * User-facing message for a QuickBooks OAuth callback error.
 * @param reason Code returned by the API (`oauth`, `missing_params`, etc.).
 * @returns English label for the admin UI.
 */
export function quickBooksOAuthErrorMessage(reason?: string | null): string {
  if (reason === 'oauth') {
    return 'QuickBooks connection denied or expired.'
  }

  if (reason === 'missing_params') {
    return 'Missing OAuth parameters.'
  }

  return 'Unable to connect QuickBooks.'
}
