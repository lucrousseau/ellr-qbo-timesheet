/**
 * @file Tests for authenticated password change hook.
 */

import type { FormEvent } from 'react'
import { act } from '@testing-library/react'
import { ApiError } from '@ellr/api-client'
import { VALID_TEST_PASSWORD, VALID_TEST_PASSWORD_ALT } from '@ellr/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { changePassword } from '@ellr/api-client'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useChangePassword } from './useChangePassword'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    changePassword: vi.fn(),
    getApiErrorMessage: vi.fn((_error: unknown, fallback: string) => fallback),
  }
})

describe('useChangePassword', () => {
  beforeEach(() => {
    vi.mocked(changePassword).mockReset()
  })

  it('submits a valid password change request', async () => {
    vi.mocked(changePassword).mockResolvedValue(undefined)

    const { result } = renderHookWithLocale(() => useChangePassword())

    act(() => {
      result.current.setCurrentPassword(VALID_TEST_PASSWORD)
      result.current.setPassword(VALID_TEST_PASSWORD_ALT)
      result.current.setPasswordConfirmation(VALID_TEST_PASSWORD_ALT)
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(changePassword).toHaveBeenCalledWith(
      VALID_TEST_PASSWORD,
      VALID_TEST_PASSWORD_ALT,
      VALID_TEST_PASSWORD_ALT,
    )
    expect(result.current.success).toContain('Password updated')
    expect(result.current.currentPassword).toBe('')
  })

  it('maps api validation errors to the form error state', async () => {
    vi.mocked(changePassword).mockRejectedValue(
      new ApiError(422, 'The password confirmation does not match.', 'validation_error'),
    )

    const { result } = renderHookWithLocale(() => useChangePassword())

    act(() => {
      result.current.setCurrentPassword(VALID_TEST_PASSWORD)
      result.current.setPassword(VALID_TEST_PASSWORD_ALT)
      result.current.setPasswordConfirmation(VALID_TEST_PASSWORD_ALT)
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(result.current.error).toBeTruthy()
    expect(result.current.success).toBeNull()
  })
})
