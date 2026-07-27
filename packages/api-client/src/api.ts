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

let csrfPrimed = false

/** @internal Test helper */
export function resetCsrfStateForTests(): void {
  csrfPrimed = false
}

function resolveCsrfUrl(): string {
  try {
    const apiUrl = new URL(API_URL)
    const basePath = apiUrl.pathname.replace(/\/api\/?$/, '')
    apiUrl.pathname = `${basePath}/sanctum/csrf-cookie`.replace(/\/{2,}/g, '/')
    return apiUrl.toString()
  } catch {
    return API_URL.replace(/\/api\/?$/, '/sanctum/csrf-cookie')
  }
}

function readXsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  if (!match?.[1]) {
    return null
  }

  return decodeURIComponent(match[1])
}

function isMutatingMethod(method?: string): boolean {
  const normalized = (method ?? 'GET').toUpperCase()
  return normalized !== 'GET' && normalized !== 'HEAD' && normalized !== 'OPTIONS'
}

export async function ensureCsrfCookie(): Promise<void> {
  await fetch(resolveCsrfUrl(), {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
  csrfPrimed = true
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const { headers: initHeaders, credentials: initCredentials, method, ...rest } = init ?? {}
  const normalizedMethod = method ?? 'GET'
  let csrfHeaders: Record<string, string> = {}

  if (isMutatingMethod(normalizedMethod)) {
    if (!csrfPrimed || readXsrfToken() === null) {
      await ensureCsrfCookie()
    }

    const xsrfToken = readXsrfToken()
    if (xsrfToken) {
      csrfHeaders['X-XSRF-TOKEN'] = xsrfToken
    }
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...rest,
    method: normalizedMethod,
    credentials: initCredentials ?? 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...csrfHeaders,
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
      if (error.code === 'qbo_employee_not_configured') {
        return 'Employé QuickBooks non configuré. Contactez un administrateur.'
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
