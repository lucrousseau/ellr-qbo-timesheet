/**
 * @file Low-level HTTP helpers, CSRF cookies, and API error mapping.
 */

/**
 * HTTP error returned by the Laravel API with an optional business code.
 */
export class ApiError extends Error {
  readonly status: number
  readonly code?: string

  /**
   * Builds an API error with HTTP status and optional business code.
   * @param status HTTP status code from the response.
   * @param message Human-readable error message.
   * @param code Optional API `error` code (e.g. `quickbooks_not_connected`).
   */
  constructor(status: number, message: string, code?: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

/** Base URL for the Laravel API (`/api`). */
export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

let csrfPrimed = false

/**
 * Resets CSRF state between tests.
 * @internal
 */
export function resetCsrfStateForTests(): void {
  csrfPrimed = false
}

/**
 * Builds the Sanctum CSRF cookie URL from `API_URL`.
 * @returns Absolute URL for `/sanctum/csrf-cookie`.
 */
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

/**
 * Reads the `XSRF-TOKEN` value from document cookies.
 * @returns Decoded token or `null` when absent or in a non-DOM environment.
 */
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

/**
 * Returns whether the HTTP method mutates server state (non-safe).
 * @param method HTTP method name (defaults to `GET`).
 * @returns `true` for POST, PUT, PATCH, DELETE, etc.
 */
function isMutatingMethod(method?: string): boolean {
  const normalized = (method ?? 'GET').toUpperCase()
  return normalized !== 'GET' && normalized !== 'HEAD' && normalized !== 'OPTIONS'
}

/**
 * Fetches the Sanctum CSRF cookie before a mutating request.
 * @returns Promise resolved once the cookie is primed.
 */
export async function ensureCsrfCookie(): Promise<void> {
  await fetch(resolveCsrfUrl(), {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
  csrfPrimed = true
}

/**
 * JSON HTTP client for the Laravel API (session cookies + Sanctum CSRF).
 * @param path Path relative to `API_URL` (e.g. `/user`).
 * @param init `fetch` options (method, body, headers).
 * @returns Typed JSON body or `undefined` for a 204 response.
 */
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

/**
 * Parses a failed `fetch` response into an `ApiError`.
 * @param response Non-OK HTTP response from the API.
 * @returns Rejected-error payload with status, message, and optional code.
 */
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

/**
 * Normalizes `HeadersInit` into a plain string record for merging.
 * @param headers Optional fetch headers in any supported shape.
 * @returns Header map suitable for object spread.
 */
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
