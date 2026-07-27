import { apiFetch } from './api'

export type QuickBooksStatus = {
  connected: boolean
  realm_id?: string
  access_token_expires_at?: string | null
}

export type QuickBooksConnectResponse = {
  authorization_url: string
}

export async function fetchQuickBooksStatus(): Promise<QuickBooksStatus> {
  return apiFetch<QuickBooksStatus>('/quickbooks/status')
}

export async function connectQuickBooks(): Promise<QuickBooksConnectResponse> {
  return apiFetch<QuickBooksConnectResponse>('/quickbooks/connect')
}

export async function disconnectQuickBooks(): Promise<{ connected: boolean }> {
  return apiFetch<{ connected: boolean }>('/quickbooks/disconnect', { method: 'POST' })
}

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

export function quickBooksOAuthErrorMessage(reason?: string | null): string {
  if (reason === 'oauth') {
    return 'Connexion QuickBooks refusée ou expirée.'
  }

  if (reason === 'missing_params') {
    return 'Paramètres OAuth manquants.'
  }

  return 'Connexion QuickBooks impossible.'
}
