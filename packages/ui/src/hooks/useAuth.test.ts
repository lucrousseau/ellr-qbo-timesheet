/**
 * @file Tests for session bootstrap and sign-in/out hook.
 */

import type { FormEvent } from 'react'
import { act, waitFor } from '@testing-library/react'
import { ApiError } from '@ellr/api-client'
import { VALID_TEST_PASSWORD } from '@ellr/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchCurrentUser, login, logout } from '@ellr/api-client'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useAuth } from './useAuth'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    fetchCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
  }
})

const signedInUser = {
  id: 1,
  name: 'Jane',
  email: 'jane@example.com',
  locale: 'en' as const,
}

describe('useAuth', () => {
  beforeEach(() => {
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(login).mockReset()
    vi.mocked(logout).mockReset()
  })

  it('bootstraps the current user on mount', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(signedInUser)

    const { result } = renderHookWithLocale(() => useAuth())

    await waitFor(() => {
      expect(result.current.authLoading).toBe(false)
    })

    expect(result.current.user).toEqual(signedInUser)
    expect(result.current.bootstrapError).toBeNull()
  })

  it('maps bootstrap failures to localized api errors', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(401, 'API error: 401'))

    const { result } = renderHookWithLocale(() => useAuth())

    await waitFor(() => {
      expect(result.current.authLoading).toBe(false)
    })

    expect(result.current.user).toBeNull()
    expect(result.current.bootstrapError).toBe('Session expired. Please sign in again.')
  })

  it('signs in and clears the password field', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(401, 'API error: 401'))
    vi.mocked(login).mockResolvedValue(signedInUser)

    const { result } = renderHookWithLocale(() => useAuth())

    await waitFor(() => {
      expect(result.current.authLoading).toBe(false)
    })

    act(() => {
      result.current.setEmail('jane@example.com')
      result.current.setPassword(VALID_TEST_PASSWORD)
    })

    await act(async () => {
      await result.current.handleLogin({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(login).toHaveBeenCalledWith('jane@example.com', VALID_TEST_PASSWORD)
    expect(result.current.user).toEqual(signedInUser)
    expect(result.current.password).toBe('')
  })

  it('signs out and clears the session user', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(signedInUser)
    vi.mocked(logout).mockResolvedValue(undefined)

    const { result } = renderHookWithLocale(() => useAuth())

    await waitFor(() => {
      expect(result.current.user).toEqual(signedInUser)
    })

    await act(async () => {
      await result.current.handleLogout()
    })

    expect(logout).toHaveBeenCalled()
    expect(result.current.user).toBeNull()
  })
})
