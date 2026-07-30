import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiFetch, ensureCsrfCookie, resetCsrfStateForTests } from './api'

/** Matches packages/password-policy/test-passwords.json alternate (tests only). */
const VALID_TEST_PASSWORD_ALT = 'EllrNew!2026'
import {
  createTimesheetUser,
  deleteTimesheetUser,
  fetchAdminQboCustomers,
  fetchQboEmployees,
  fetchTimesheetUserCustomers,
  fetchTimesheetUsers,
  syncTimesheetUserCustomers,
} from './admin'
import { DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS, fetchAppConfig } from './appConfig'
import {
  changePassword,
  fetchCurrentUser,
  login,
  logout,
  register,
  requestPasswordReset,
  resendVerificationEmail,
  resetPassword,
  updateQboEmployee,
  updateUserLocale,
  updateUserQboEmployee,
} from './auth'
import {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchQuickBooksStatus,
  parseQuickBooksOAuthCallback,
} from './quickbooks'
import { createTimeActivity, discardTimeTracker, fetchTimeTracker, listTimeActivities, logTimeTracker, updateTimeActivity, updateTimeTracker } from './timesheet'
import { listAdminUserTimeActivities, updateAdminUserTimeActivity } from './admin'
import {
  createSuperAdminOrganization,
  deleteSuperAdminOrganization,
  fetchSuperAdminOrganizations,
  updateSuperAdminOrganization,
} from './superAdmin'
import {
  hasValidPasswordResetInvite,
  isEmailUnverified,
  isResetPasswordRoute,
  parseEmailVerificationCallback,
  parseResetPasswordParams,
  shouldBlockUnverifiedUser,
} from './authRecovery'

describe('apiFetch', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.cookie = ''
    resetCsrfStateForTests()
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

  it('does not prime csrf or send xsrf token on safe methods', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/health', { method: 'GET' })
    await apiFetch('/health', { method: 'HEAD' })
    await apiFetch('/health', { method: 'OPTIONS' })

    expect(fetchMock).toHaveBeenCalledTimes(3)
    for (const call of fetchMock.mock.calls) {
      expect(call[1]?.headers).not.toHaveProperty('X-XSRF-TOKEN')
    }
  })

  it('reuses csrf priming on subsequent mutating requests', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('reuse-token')}`
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })
    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenCalledTimes(3)
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
    expect(fetchMock).toHaveBeenNthCalledWith(
      3,
      'http://localhost:8000/api/logout',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('reads the xsrf token when it is the first cookie', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('first-cookie-token')}`
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': 'first-cookie-token',
        }),
      }),
    )
  })

  it('reads the xsrf token when it is not the first cookie', async () => {
    document.cookie = 'session=abc'
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('later-cookie-token')}`
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': 'later-cookie-token',
        }),
      }),
    )
  })

  it('builds relative csrf and api urls when VITE_API_URL is /api', async () => {
    vi.resetModules()
    vi.stubEnv('VITE_API_URL', '/api')

    const { apiFetch, ensureCsrfCookie, resetCsrfStateForTests } = await import('./api')
    resetCsrfStateForTests()

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('proxy-token')}`

    await ensureCsrfCookie()
    await apiFetch('/login', {
      method: 'POST',
      body: JSON.stringify({ email: 'jane@example.com', password: 'password' }),
    })

    expect(fetchMock).toHaveBeenNthCalledWith(1, '/sanctum/csrf-cookie', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      '/api/login',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': 'proxy-token',
        }),
      }),
    )

    vi.resetModules()
  })

  it('builds the csrf cookie url for nested api base paths', async () => {
    vi.resetModules()
    vi.stubEnv('VITE_API_URL', 'http://localhost:8000/backend/api')

    const { apiFetch: isolatedApiFetch, resetCsrfStateForTests: resetCsrf } = await import('./api')
    resetCsrf()

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('nested-token')}`

    await isolatedApiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      'http://localhost:8000/backend/sanctum/csrf-cookie',
      expect.objectContaining({ credentials: 'include' }),
    )

    vi.resetModules()
  })

  it('falls back to a relative csrf cookie path when api url parsing fails', async () => {
    vi.resetModules()
    vi.stubEnv('VITE_API_URL', 'relative/api')

    const { ensureCsrfCookie: isolatedEnsureCsrf, resetCsrfStateForTests: resetCsrf } = await import('./api')
    resetCsrf()

    const fetchMock = vi.fn().mockResolvedValue({ ok: true })
    vi.stubGlobal('fetch', fetchMock)

    await isolatedEnsureCsrf()

    expect(fetchMock).toHaveBeenCalledWith('relative/sanctum/csrf-cookie', {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    vi.resetModules()
  })

  it('returns null xsrf tokens when document is unavailable', async () => {
    vi.resetModules()
    vi.stubEnv('VITE_API_URL', 'http://localhost:8000/api')

    const originalDocument = globalThis.document
    // @ts-expect-error test-only removal of document
    delete globalThis.document

    const { apiFetch: isolatedApiFetch, resetCsrfStateForTests: resetCsrf } = await import('./api')
    resetCsrf()

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await isolatedApiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        headers: expect.not.objectContaining({
          'X-XSRF-TOKEN': expect.anything(),
        }),
      }),
    )

    globalThis.document = originalDocument
    vi.resetModules()
  })

  it('ignores malformed xsrf cookie values', async () => {
    document.cookie = 'XSRF-TOKEN='
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        headers: expect.not.objectContaining({
          'X-XSRF-TOKEN': expect.anything(),
        }),
      }),
    )
  })

  it('reads the xsrf token when the cookie omits whitespace after the semicolon', async () => {
    const cookieDescriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie')
    Object.defineProperty(document, 'cookie', {
      configurable: true,
      get: () => `session=abc;XSRF-TOKEN=${encodeURIComponent('compact-cookie-token')}`,
      set: () => undefined,
    })

    try {
      const fetchMock = vi
        .fn()
        .mockResolvedValueOnce({ ok: true })
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({}),
        })
      vi.stubGlobal('fetch', fetchMock)

      await apiFetch('/logout', { method: 'POST' })

      expect(fetchMock).toHaveBeenNthCalledWith(
        2,
        'http://localhost:8000/api/logout',
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-XSRF-TOKEN': 'compact-cookie-token',
          }),
        }),
      )
    } finally {
      if (cookieDescriptor) {
        Object.defineProperty(document, 'cookie', cookieDescriptor)
      }
    }
  })

  it('re-primes csrf when the xsrf cookie disappears between mutating requests', async () => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('first-token')}`
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({}),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })

    document.cookie = 'XSRF-TOKEN=; Max-Age=0'

    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenCalledTimes(4)
    expect(fetchMock).toHaveBeenNthCalledWith(1, 'http://localhost:8000/sanctum/csrf-cookie', expect.anything())
    expect(fetchMock).toHaveBeenNthCalledWith(3, 'http://localhost:8000/sanctum/csrf-cookie', expect.anything())
    expect(fetchMock).toHaveBeenNthCalledWith(
      4,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        headers: expect.not.objectContaining({
          'X-XSRF-TOKEN': expect.anything(),
        }),
      }),
    )
  })

  it('defaults to GET when the method is omitted', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/health')

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/health',
      expect.objectContaining({ method: 'GET' }),
    )
  })
})

describe('auth helpers', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.cookie = ''
    resetCsrfStateForTests()
  })

  function mockCsrfCookie(token = 'csrf-token-value') {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}`
  }

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

  it('sends the xsrf token header on mutating requests', async () => {
    mockCsrfCookie('encoded-token')
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ message: 'ok' }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await apiFetch('/logout', { method: 'POST' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/logout',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': 'encoded-token',
        }),
      }),
    )
  })

  it('updates the user locale preference', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          user: {
            id: 1,
            name: 'Jane',
            email: 'jane@example.com',
            locale: 'fr',
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(updateUserLocale('fr')).resolves.toEqual({
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      locale: 'fr',
    })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/user/locale',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({ locale: 'fr' }),
      }),
    )
  })

  it('changes the signed-in user password', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ message: 'Password updated successfully.' }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      changePassword('OldPassword!1', 'NewPassword!2', 'NewPassword!2'),
    ).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/user/password',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          current_password: 'OldPassword!1',
          password: 'NewPassword!2',
          password_confirmation: 'NewPassword!2',
        }),
      }),
    )
  })

  it('logs in and returns the user', async () => {
    mockCsrfCookie()
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

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/login',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ email: 'jane@example.com', password: 'password' }),
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': expect.any(String),
        }),
      }),
    )
  })

  it('registers a user and returns the created account', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({
          user: {
            id: 1,
            name: 'Jane',
            email: 'jane@example.com',
            organization: {
              id: 1,
              name: 'Acme Inc',
              slug: 'acme-inc',
              qbo_connected: false,
            },
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      register({
        name: 'Jane',
        email: 'jane@example.com',
        password: 'password',
        password_confirmation: 'password',
        organization_name: 'Acme Inc',
      }),
    ).resolves.toEqual({
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      organization: {
        id: 1,
        name: 'Acme Inc',
        slug: 'acme-inc',
        qbo_connected: false,
      },
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/register',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          name: 'Jane',
          email: 'jane@example.com',
          password: 'password',
          password_confirmation: 'password',
          organization_name: 'Acme Inc',
        }),
      }),
    )
  })

  it('returns null when the current user is unauthorized', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchCurrentUser()).resolves.toBeNull()
    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8000/api/user', expect.any(Object))
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
    mockCsrfCookie()
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
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'X-XSRF-TOKEN': expect.any(String),
        }),
      }),
    )
  })

  it('updates the qbo employee mapping', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          user: {
            id: 1,
            name: 'Jane',
            email: 'jane@example.com',
            qbo_employee_ref: '7',
            qbo_employee_name: 'Jane Doe',
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(updateQboEmployee('7', 'Jane Doe')).resolves.toEqual({
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      qbo_employee_ref: '7',
      qbo_employee_name: 'Jane Doe',
    })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/user/qbo-employee',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          qbo_employee_ref: '7',
          qbo_employee_name: 'Jane Doe',
        }),
      }),
    )
  })

  it('updates another user qbo employee mapping for administrators', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          user: {
            id: 2,
            name: 'Bob',
            email: 'bob@example.com',
            qbo_employee_ref: '9',
            qbo_employee_name: 'Bob Smith',
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(updateUserQboEmployee(2, '9', 'Bob Smith')).resolves.toEqual({
      id: 2,
      name: 'Bob',
      email: 'bob@example.com',
      qbo_employee_ref: '9',
      qbo_employee_name: 'Bob Smith',
    })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/admin/users/2/qbo-employee',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({
          qbo_employee_ref: '9',
          qbo_employee_name: 'Bob Smith',
        }),
      }),
    )
  })

  it('requests a password reset link', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(requestPasswordReset('user@example.com')).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/forgot-password',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ email: 'user@example.com' }),
      }),
    )
  })

  it('requests a password reset link for a specific client', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(requestPasswordReset('user@example.com', { client: 'admin' })).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/forgot-password',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ email: 'user@example.com', client: 'admin' }),
      }),
    )
  })

  it('resets the password from an email link', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      resetPassword({
        token: 'reset-token',
        email: 'user@example.com',
        password: VALID_TEST_PASSWORD_ALT,
        passwordConfirmation: VALID_TEST_PASSWORD_ALT,
      }),
    ).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/reset-password',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          token: 'reset-token',
          email: 'user@example.com',
          password: VALID_TEST_PASSWORD_ALT,
          password_confirmation: VALID_TEST_PASSWORD_ALT,
        }),
      }),
    )
  })

  it('resends the verification email', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(resendVerificationEmail()).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/email/verification-notification',
      expect.objectContaining({
        method: 'POST',
      }),
    )
  })
})

describe('app config', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfStateForTests()
  })

  it('loads require_email_verification from the health endpoint', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'ok',
        service: 'ellr-qbo-timesheet',
        require_email_verification: true,
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchAppConfig()).resolves.toEqual({
      require_email_verification: true,
      time_tracker_max_accumulated_seconds: DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/health',
      expect.objectContaining({
        credentials: 'include',
      }),
    )
  })

  it('reads the timer cap from the health endpoint when present', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'ok',
        service: 'ellr-qbo-timesheet',
        require_email_verification: false,
        time_tracker_max_accumulated_seconds: 7200,
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchAppConfig()).resolves.toEqual({
      require_email_verification: false,
      time_tracker_max_accumulated_seconds: 7200,
    })
  })
})

describe('quickbooks api', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    resetCsrfStateForTests()
  })

  it('parses oauth callback search params', () => {
    expect(parseQuickBooksOAuthCallback('?quickbooks=connected')).toEqual({ result: 'connected' })
    expect(parseQuickBooksOAuthCallback('?quickbooks=error&reason=oauth')).toEqual({
      result: 'error',
      reason: 'oauth',
    })
    expect(parseQuickBooksOAuthCallback('')).toEqual({ result: null })
  })

  it('loads quickbooks status from the api', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ connected: true, realm_id: 'realm-1' }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchQuickBooksStatus()).resolves.toEqual({
      connected: true,
      realm_id: 'realm-1',
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/status',
      expect.objectContaining({
        credentials: 'include',
      }),
    )
  })

  it('starts quickbooks connect flow', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ authorization_url: 'https://intuit.example/oauth' }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(connectQuickBooks()).resolves.toEqual({
      authorization_url: 'https://intuit.example/oauth',
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/connect',
      expect.objectContaining({
        credentials: 'include',
      }),
    )
  })

  it('disconnects quickbooks through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ connected: false }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(disconnectQuickBooks()).resolves.toEqual({ connected: false })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/quickbooks/disconnect',
      expect.objectContaining({
        method: 'POST',
      }),
    )
  })

  function mockCsrfCookie(token = 'csrf-token-value') {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}`
  }
})

describe('timesheet api', () => {
  function mockCsrfCookie(token = 'csrf-token-value') {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}`
  }

  afterEach(() => {
    vi.unstubAllGlobals()
    document.cookie = ''
    resetCsrfStateForTests()
  })

  it('creates a time activity through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ data: { Id: '99' } }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      createTimeActivity({
        start_time: '2026-07-27T09:00:00',
        end_time: '2026-07-27T17:00:00',
      }),
    ).resolves.toEqual({ Id: '99' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/time-activities',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          start_time: '2026-07-27T09:00:00',
          end_time: '2026-07-27T17:00:00',
        }),
      }),
    )
  })

  it('lists time activities with optional pagination query params', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: [{ Id: '1' }],
        meta: {
          count: 1,
          max_results: 10,
          start_position: 3,
          truncated: false,
        },
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(listTimeActivities({ start_position: 3, max_results: 10 })).resolves.toEqual({
      data: [{ Id: '1' }],
      meta: {
        count: 1,
        max_results: 10,
        start_position: 3,
        truncated: false,
      },
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/time-activities?start_position=3&max_results=10',
      expect.objectContaining({ method: 'GET' }),
    )
  })

  it('updates a time activity through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: { Id: '12', BillableStatus: 'Billable' } }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      updateTimeActivity('12', {
        description: 'Updated',
        is_billable: true,
      }),
    ).resolves.toEqual({ Id: '12', BillableStatus: 'Billable' })

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/time-activities/12',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({ description: 'Updated', is_billable: true }),
      }),
    )
  })

  it('lists administrator time activities for a provisioned user', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: [{ Id: '1' }],
        meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(listAdminUserTimeActivities(4, { max_results: 10 })).resolves.toMatchObject({
      data: [{ Id: '1' }],
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/admin/users/4/time-activities?max_results=10',
      expect.objectContaining({ method: 'GET' }),
    )
  })

  it('updates administrator time activities for a provisioned user', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: { Id: '9' } }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(updateAdminUserTimeActivity(4, '9', { is_billable: false })).resolves.toEqual({ Id: '9' })

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/admin/users/4/time-activities/9',
      expect.objectContaining({
        method: 'PATCH',
        body: JSON.stringify({ is_billable: false }),
      }),
    )
  })

  it('loads the active timer session from the api', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: {
          customer_ref: '11',
          customer_name: 'Acme Corp',
          project_ref: null,
          project_name: null,
          service_ref: null,
          service_name: null,
          description: null,
          is_billable: false,
          accumulated_seconds: 120,
          running_since: null,
          elapsed_seconds: 120,
          is_running: false,
        },
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchTimeTracker()).resolves.toMatchObject({
      customer_ref: '11',
      elapsed_seconds: 120,
      is_running: false,
    })
  })

  it('updates the active timer session through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: {
            customer_ref: null,
            customer_name: null,
            project_ref: null,
            project_name: null,
            service_ref: null,
            service_name: null,
            description: 'Support',
            is_billable: false,
            accumulated_seconds: 0,
            running_since: '2026-07-28T12:00:00Z',
            elapsed_seconds: 0,
            is_running: true,
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      updateTimeTracker({
        description: 'Support',
        is_running: true,
      }),
    ).resolves.toMatchObject({
      description: 'Support',
      is_running: true,
    })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/time-tracker',
      expect.objectContaining({
        method: 'PUT',
        body: JSON.stringify({
          description: 'Support',
          is_running: true,
        }),
      }),
    )
  })

  it('logs the active timer session through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ data: { Id: '77' } }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(logTimeTracker()).resolves.toEqual({ Id: '77' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/time-tracker/log',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('discards the active timer session through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({ ok: true, status: 204 })
    vi.stubGlobal('fetch', fetchMock)

    await expect(discardTimeTracker()).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/time-tracker',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })
})

describe('admin api helpers', () => {
  function mockCsrfCookie(token = 'csrf-token-value') {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}`
  }

  afterEach(() => {
    vi.unstubAllGlobals()
    document.cookie = ''
    resetCsrfStateForTests()
  })

  it('loads quickbooks employees from the api', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: [{ id: '7', display_name: 'Jane Doe', email: 'jane@example.com' }],
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchQboEmployees()).resolves.toEqual([
      { id: '7', display_name: 'Jane Doe', email: 'jane@example.com' },
    ])

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/employees',
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('requests a quickbooks employee refresh when asked', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ data: [] }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await fetchQboEmployees({ refresh: true })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/employees?refresh=1',
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('forwards an abort signal when loading quickbooks employees', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ data: [] }),
    })
    vi.stubGlobal('fetch', fetchMock)
    const controller = new AbortController()

    await fetchQboEmployees({ refresh: true, signal: controller.signal })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/employees?refresh=1',
      expect.objectContaining({ signal: controller.signal }),
    )
  })

  it('loads provisioned timesheet users from the api', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: [{ id: 2, name: 'Jane Doe', email: 'jane@example.com', qbo_employee_ref: '7' }],
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchTimesheetUsers()).resolves.toEqual([
      { id: 2, name: 'Jane Doe', email: 'jane@example.com', qbo_employee_ref: '7' },
    ])
  })

  it('creates a timesheet user through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({
          user: { id: 2, name: 'Jane Doe', email: 'jane@example.com', qbo_employee_ref: '7' },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      createTimesheetUser({
        qbo_employee_ref: '7',
      }),
    ).resolves.toEqual({
      id: 2,
      name: 'Jane Doe',
      email: 'jane@example.com',
      qbo_employee_ref: '7',
    })
  })

  it('removes a provisioned timesheet user through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({ ok: true, status: 204 })
    vi.stubGlobal('fetch', fetchMock)

    await expect(deleteTimesheetUser(2)).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/admin/users/2',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })

  it('loads quickbooks customers for administrator assignment pickers', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        data: [{ id: '11', display_name: 'Acme Corp' }],
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchAdminQboCustomers({ refresh: true })).resolves.toEqual([
      { id: '11', display_name: 'Acme Corp' },
    ])
  })

  it('loads and syncs timesheet user customer assignments', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          all_customers_access: false,
          data: [{ id: '11', display_name: 'Acme Corp' }],
        }),
      })
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          all_customers_access: false,
          data: [{ id: '11', display_name: 'Acme Corp' }],
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchTimesheetUserCustomers(2)).resolves.toEqual({
      all_customers_access: false,
      data: [{ id: '11', display_name: 'Acme Corp' }],
    })

    await expect(
      syncTimesheetUserCustomers(2, {
        all_customers_access: false,
        customer_refs: ['11'],
      }),
    ).resolves.toEqual({
      all_customers_access: false,
      data: [{ id: '11', display_name: 'Acme Corp' }],
    })
  })

  it('loads and mutates tenant organizations for platform super administrators', async () => {
    mockCsrfCookie()
    const organization = {
      id: 5,
      name: 'Gamma LLC',
      slug: 'gamma-llc',
      qbo_connected: false,
      users_count: 1,
      created_at: '2026-07-30T12:00:00Z',
    }
    const csrfResponse = { ok: true, status: 204, json: async () => ({}) }
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: [organization] }),
      })
      .mockResolvedValueOnce(csrfResponse)
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({ data: organization }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: { ...organization, name: 'Gamma Corp' } }),
      })
      .mockResolvedValueOnce({ ok: true, status: 204 })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchSuperAdminOrganizations()).resolves.toEqual([organization])

    await expect(
      createSuperAdminOrganization({
        organization_name: 'Gamma LLC',
        name: 'Gamma Admin',
        email: 'gamma@client.test',
        password: VALID_TEST_PASSWORD_ALT,
        password_confirmation: VALID_TEST_PASSWORD_ALT,
      }),
    ).resolves.toEqual(organization)

    await expect(updateSuperAdminOrganization(5, { name: 'Gamma Corp' })).resolves.toEqual({
      ...organization,
      name: 'Gamma Corp',
    })

    await expect(deleteSuperAdminOrganization(5)).resolves.toBeUndefined()

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/super-admin/organizations/5',
      expect.objectContaining({ method: 'DELETE' }),
    )
  })
})

describe('auth recovery helpers', () => {
  it('parses email verification callbacks', () => {
    expect(parseEmailVerificationCallback('?email=verified')).toEqual({ result: 'verified' })
    expect(parseEmailVerificationCallback('?email=already_verified')).toEqual({
      result: 'already_verified',
    })
    expect(parseEmailVerificationCallback('?email=error&reason=expired')).toEqual({
      result: 'error',
      reason: 'expired',
    })
    expect(parseEmailVerificationCallback('?email=unknown')).toEqual({ result: null })
  })

  it('detects reset password routes and query params', () => {
    expect(isResetPasswordRoute('/reset-password')).toBe(true)
    expect(isResetPasswordRoute('/app/reset-password')).toBe(true)
    expect(isResetPasswordRoute('/app/reset-password/')).toBe(true)
    expect(isResetPasswordRoute('/prefix/reset-password')).toBe(true)
    expect(isResetPasswordRoute('/not-reset-password')).toBe(false)
    expect(isResetPasswordRoute('/reset-password-extra')).toBe(false)
    expect(parseResetPasswordParams('?token=abc&email=user%40example.com')).toEqual({
      token: 'abc',
      email: 'user@example.com',
    })
  })

  it('detects complete password reset invites from the current url', () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/reset-password',
        search: '?token=abc&email=user%40example.com',
      },
    })

    expect(hasValidPasswordResetInvite()).toBe(true)

    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/reset-password',
        search: '',
      },
    })

    expect(hasValidPasswordResetInvite()).toBe(false)

    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/',
        search: '?token=abc&email=user%40example.com',
      },
    })

    expect(hasValidPasswordResetInvite()).toBe(false)
  })

  it('detects unverified users and blocking rules', () => {
    expect(isEmailUnverified({ email_verified_at: null })).toBe(true)
    expect(isEmailUnverified({ email_verified_at: '' })).toBe(true)
    expect(isEmailUnverified({ email_verified_at: '2026-01-01T00:00:00Z' })).toBe(false)
    expect(shouldBlockUnverifiedUser({ email_verified_at: null }, true)).toBe(true)
    expect(shouldBlockUnverifiedUser({ email_verified_at: null }, false)).toBe(false)
  })
})
