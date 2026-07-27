export class ApiError extends Error {
  readonly status: number
  readonly code?: string

  constructor(status: number, message: string, code?: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

const CSRF_URL = API_URL.replace(/\/api$/, '/sanctum/csrf-cookie')

export async function ensureCsrfCookie(): Promise<void> {
  await fetch(CSRF_URL, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const { headers: initHeaders, credentials: initCredentials, ...rest } = init ?? {}

  const response = await fetch(`${API_URL}${path}`, {
    ...rest,
    credentials: initCredentials ?? 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...normalizeHeaders(initHeaders),
    },
  })

  if (!response.ok) {
    throw await createApiError(response)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return response.json() as Promise<T>
}

async function createApiError(response: Response): Promise<ApiError> {
  let message = `API error: ${response.status}`
  let code: string | undefined

  const contentType = response.headers?.get('content-type')
  if (contentType?.includes('application/json')) {
    try {
      const body = (await response.json()) as { message?: string; error?: string }
      if (body.message) {
        message = body.message
      }
      if (typeof body.error === 'string') {
        code = body.error
      }
    } catch {
      // Ignore malformed JSON error bodies.
    }
  }

  return new ApiError(response.status, message, code)
}

function normalizeHeaders(headers?: HeadersInit): Record<string, string> {
  if (!headers) {
    return {}
  }

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries())
  }

  if (Array.isArray(headers)) {
    return Object.fromEntries(headers)
  }

  return headers
}

export function getApiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    if (error.status === 401) {
      return 'Session expirée. Reconnectez-vous.'
    }
    if (error.status === 403) {
      if (error.code === 'quickbooks_not_connected') {
        return 'QuickBooks n\'est pas connecté. Connectez-le depuis l\'interface admin.'
      }
      if (error.code === 'quickbooks_expired') {
        return 'Connexion QuickBooks expirée. Reconnectez-la depuis l\'interface admin.'
      }
      if (error.code === 'registration_disabled') {
        return 'Inscription désactivée.'
      }
      return 'Accès refusé.'
    }
    if (error.status === 422) {
      return 'Données invalides ou erreur QuickBooks.'
    }
    if (error.status === 503) {
      return 'QuickBooks est occupé. Réessayez dans un instant.'
    }
  }

  if (error instanceof TypeError) {
    return 'Impossible de joindre l\'API Laravel.'
  }

  return fallback
}
