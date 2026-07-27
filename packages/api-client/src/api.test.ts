import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiFetch, ensureCsrfCookie, getApiErrorMessage } from './api'
import { fetchCurrentUser, login, logout } from './auth'

describe('apiFetch', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('returns parsed json for successful responses', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ status: 'ok' }),
      }),
    )

    await expect(apiFetch('/health')).resolves.toEqual({ status: 'ok' })
  })

  it('normalizes header objects for fetch requests', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    const headers = new Headers({ 'X-Test': 'value' })
    await apiFetch('/health', { headers })

    expect(fetchMock).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({ 'x-test': 'value' }),
      }),
    )

    fetchMock.mockClear()

    await apiFetch('/health', {
      headers: [['X-Array', 'header']],
    })

    expect(fetchMock).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({ 'X-Array': 'header' }),
      }),
    )
  })

  it('sends credentials include by default', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/health')

    expect(fetchMock).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('throws ApiError when the api responds with an error status', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        headers: {
          get: () => null,
        },
      }),
    )

    await expect(apiFetch('/health')).rejects.toMatchObject({
      name: 'ApiError',
      status: 500,
      message: 'API error: 500',
    })
  })

  it('calls the api with the configured base url and json headers', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/health')

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/health',
      expect.objectContaining({
        credentials: 'include',
        headers: expect.objectContaining({
          Accept: 'application/json',
          'Content-Type': 'application/json',
        }),
      }),
    )
  })

  it('keeps the default error message for non-json responses', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 403,
        headers: {
          get: () => 'text/plain',
        },
      }),
    )

    await expect(apiFetch('/health')).rejects.toMatchObject({
      message: 'API error: 403',
    })
  })

  it('parses only the message when the error code is not a string', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        headers: {
          get: () => 'application/json',
        },
        json: async () => ({
          message: 'Validation failed.',
          error: { code: 'invalid' },
        }),
      }),
    )

    await expect(apiFetch('/time-activities')).rejects.toMatchObject({
      message: 'Validation failed.',
      code: undefined,
    })
  })

  it('uses the default error message when json bodies omit a message', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 400,
        headers: {
          get: () => 'application/json',
        },
        json: async () => ({ error: 'bad_request' }),
      }),
    )

    await expect(apiFetch('/health')).rejects.toMatchObject({
      message: 'API error: 400',
      code: 'bad_request',
    })
  })

  it('passes through plain header objects', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/health', {
      headers: { 'X-Custom': 'value' },
    })

    expect(fetchMock).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Custom': 'value',
          Accept: 'application/json',
        }),
      }),
    )
  })

  it('parses api error codes from json responses', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 403,
        headers: {
          get: () => 'application/json',
        },
        json: async () => ({
          message: 'QuickBooks is not connected.',
          error: 'quickbooks_not_connected',
        }),
      }),
    )

    await expect(apiFetch('/time-activities')).rejects.toMatchObject({
      status: 403,
      code: 'quickbooks_not_connected',
      message: 'QuickBooks is not connected.',
    })
  })

  it('returns undefined for no-content responses', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 204,
      }),
    )

    await expect(apiFetch('/resource')).resolves.toBeUndefined()
  })
})

describe('getApiErrorMessage', () => {
  it('maps unauthorized responses to a session message', () => {
    expect(getApiErrorMessage(new ApiError(401, 'API error: 401'), 'fallback')).toBe(
      'Session expirée. Reconnectez-vous.',
    )
  })

  it('maps forbidden responses to an access message', () => {
    expect(getApiErrorMessage(new ApiError(403, 'API error: 403'), 'fallback')).toBe(
      'Accès refusé.',
    )
  })

  it('maps quickbooks error codes on forbidden responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'quickbooks_not_connected'), 'fallback'),
    ).toBe('QuickBooks n\'est pas connecté. Connectez-le depuis l\'interface admin.')

    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'quickbooks_expired'), 'fallback'),
    ).toBe('Connexion QuickBooks expirée. Reconnectez-la depuis l\'interface admin.')
  })

  it('maps registration disabled responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'registration_disabled'), 'fallback'),
    ).toBe('Inscription désactivée.')
  })

  it('maps validation responses to a quickbooks message', () => {
    expect(getApiErrorMessage(new ApiError(422, 'API error: 422'), 'fallback')).toBe(
      'Données invalides ou erreur QuickBooks.',
    )
  })

  it('uses the fallback for unknown errors', () => {
    expect(getApiErrorMessage(new Error('boom'), 'fallback')).toBe('fallback')
  })

  it('maps service unavailable responses to a retry message', () => {
    expect(getApiErrorMessage(new ApiError(503, 'API error: 503'), 'fallback')).toBe(
      'QuickBooks est occupé. Réessayez dans un instant.',
    )
  })

  it('maps network failures to an api outage message', () => {
    expect(getApiErrorMessage(new TypeError('failed to fetch'), 'fallback')).toBe(
      'Impossible de joindre l\'API Laravel.',
    )
  })
})

describe('auth helpers', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('primes the csrf cookie endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', fetchMock)

    await ensureCsrfCookie()

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/sanctum/csrf-cookie',
      expect.objectContaining({
        credentials: 'include',
        headers: { Accept: 'application/json' },
      }),
    )
  })

  it('logs in and returns the user', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ user: { id: 1, name: 'Jane', email: 'jane@example.com' } }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(login('jane@example.com', 'password')).resolves.toEqual({
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
    })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/login',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ email: 'jane@example.com', password: 'password' }),
      }),
    )
  })

  it('returns null when the current user is unauthorized', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: false,
        status: 401,
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchCurrentUser()).resolves.toBeNull()
    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      'http://localhost:8000/sanctum/csrf-cookie',
      expect.objectContaining({ credentials: 'include' }),
    )
    expect(fetchMock).toHaveBeenNthCalledWith(2, 'http://localhost:8000/api/user', expect.any(Object))
  })

  it('rethrows unexpected current user errors', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
      }),
    )

    await expect(fetchCurrentUser()).rejects.toBeInstanceOf(ApiError)
  })

  it('logs out through the api', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ message: 'ok' }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(logout()).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      'http://localhost:8000/sanctum/csrf-cookie',
      expect.objectContaining({ credentials: 'include' }),
    )
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({ method: 'POST' }),
    )
  })
})
