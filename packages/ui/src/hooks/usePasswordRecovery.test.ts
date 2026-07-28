/**
 * @file Tests for password recovery hook state and API orchestration.
 */

import type { FormEvent } from 'react'
import { act } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { requestPasswordReset, resetPassword } from '@ellr/api-client'
import { VALID_TEST_PASSWORD_ALT } from '@ellr/test-utils'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { usePasswordRecovery } from './usePasswordRecovery'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    getApiErrorMessage: vi.fn((_error: unknown, fallback: string) => fallback),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
  }
})

describe('usePasswordRecovery', () => {
  beforeEach(() => {
    vi.mocked(requestPasswordReset).mockReset()
    vi.mocked(resetPassword).mockReset()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/',
        search: '',
        replaceState: vi.fn(),
      },
    })
  })

  it('starts on the reset screen when the pathname matches', () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/reset-password',
        search: '?token=abc&email=user%40example.com',
        replaceState: vi.fn(),
      },
    })

    const { result } = renderHookWithLocale(() => usePasswordRecovery({ client: 'admin' }))

    expect(result.current.authScreen).toBe('reset-password')
    expect(result.current.resetParams).toEqual({
      token: 'abc',
      email: 'user@example.com',
    })
  })

  it('requests a reset link with the configured client', async () => {
    vi.mocked(requestPasswordReset).mockResolvedValue(undefined)

    const { result } = renderHookWithLocale(() => usePasswordRecovery({ client: 'timesheet' }))

    act(() => {
      result.current.setForgotEmail('user@example.com')
    })

    await act(async () => {
      await result.current.handleForgotPassword({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(requestPasswordReset).toHaveBeenCalledWith('user@example.com', { client: 'timesheet' })
    expect(result.current.forgotSuccess).toContain('reset link has been sent')
  })

  it('returns french success copy when locale is french', async () => {
    vi.mocked(requestPasswordReset).mockResolvedValue(undefined)

    const { result } = renderHookWithLocale(() => usePasswordRecovery({ client: 'timesheet' }), 'fr')

    act(() => {
      result.current.setForgotEmail('user@example.com')
    })

    await act(async () => {
      await result.current.handleForgotPassword({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(result.current.forgotSuccess).toContain('lien de réinitialisation')
  })

  it('resets the password from URL params', async () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/reset-password',
        search: '?token=abc&email=user%40example.com',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(resetPassword).mockResolvedValue(undefined)

    const { result } = renderHookWithLocale(() => usePasswordRecovery({ client: 'admin' }))

    act(() => {
      result.current.setResetPasswordValue(VALID_TEST_PASSWORD_ALT)
      result.current.setResetPasswordConfirmation(VALID_TEST_PASSWORD_ALT)
    })

    await act(async () => {
      await result.current.handleResetPassword({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(resetPassword).toHaveBeenCalledWith({
      token: 'abc',
      email: 'user@example.com',
      password: VALID_TEST_PASSWORD_ALT,
      passwordConfirmation: VALID_TEST_PASSWORD_ALT,
    })
    expect(result.current.resetSuccess).toContain('Password updated')
  })

  it('blocks reset when the password does not satisfy the shared policy', async () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        pathname: '/reset-password',
        search: '?token=abc&email=user%40example.com',
        replaceState: vi.fn(),
      },
    })

    const { result } = renderHookWithLocale(() => usePasswordRecovery({ client: 'admin' }))

    act(() => {
      result.current.setResetPasswordValue('weak-password')
      result.current.setResetPasswordConfirmation('weak-password')
    })

    await act(async () => {
      await result.current.handleResetPassword({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(resetPassword).not.toHaveBeenCalled()
    expect(result.current.resetError).toContain('at least')
  })
})
