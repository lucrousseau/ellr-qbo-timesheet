/**
 * @file Tests for password recovery hook state and API orchestration.
 */

import type { FormEvent } from 'react'
import { renderHook, act } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { requestPasswordReset, resetPassword } from '@ellr/api-client'
import { usePasswordRecovery } from './usePasswordRecovery'

vi.mock('@ellr/api-client', () => ({
  getApiErrorMessage: vi.fn((_error: unknown, fallback: string) => fallback),
  isResetPasswordRoute: (pathname: string) => pathname === '/reset-password',
  parseResetPasswordParams: (search: string) => {
    const params = new URLSearchParams(search)

    return {
      token: params.get('token'),
      email: params.get('email'),
    }
  },
  requestPasswordReset: vi.fn(),
  resetPassword: vi.fn(),
}))

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

    const { result } = renderHook(() => usePasswordRecovery({ client: 'admin' }))

    expect(result.current.authScreen).toBe('reset-password')
    expect(result.current.resetParams).toEqual({
      token: 'abc',
      email: 'user@example.com',
    })
  })

  it('requests a reset link with the configured client', async () => {
    vi.mocked(requestPasswordReset).mockResolvedValue(undefined)

    const { result } = renderHook(() => usePasswordRecovery({ client: 'timesheet' }))

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

    const { result } = renderHook(() => usePasswordRecovery({ client: 'admin' }))

    act(() => {
      result.current.setResetPasswordValue('new-password')
      result.current.setResetPasswordConfirmation('new-password')
    })

    await act(async () => {
      await result.current.handleResetPassword({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(resetPassword).toHaveBeenCalledWith({
      token: 'abc',
      email: 'user@example.com',
      password: 'new-password',
      passwordConfirmation: 'new-password',
    })
    expect(result.current.resetSuccess).toContain('Password updated')
  })
})
