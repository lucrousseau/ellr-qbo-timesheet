/**
 * @file Tests for authenticated email change hook.
 */

import type { FormEvent } from 'react'
import { act } from '@testing-library/react'
import { ApiError, changeEmail } from '@ellr/api-client'
import { VALID_TEST_PASSWORD } from '@ellr/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useChangeEmail } from './useChangeEmail'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    changeEmail: vi.fn(),
  }
})

describe('useChangeEmail', () => {
  beforeEach(() => {
    vi.mocked(changeEmail).mockReset()
  })

  it('submits a valid email change request', async () => {
    vi.mocked(changeEmail).mockResolvedValue({
      id: 1,
      name: 'Jane',
      email: 'after@example.com',
    })

    const onSuccess = vi.fn()
    const { result } = renderHookWithLocale(() =>
      useChangeEmail({ currentEmail: 'before@example.com', onSuccess }),
    )

    act(() => {
      result.current.setEmail('after@example.com')
      result.current.setCurrentPassword(VALID_TEST_PASSWORD)
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(changeEmail).toHaveBeenCalledWith(VALID_TEST_PASSWORD, 'after@example.com')
    expect(result.current.success).toContain('Check your inbox')
    expect(result.current.currentPassword).toBe('')
    expect(onSuccess).toHaveBeenCalledWith(
      expect.objectContaining({ email: 'after@example.com' }),
    )
  })

  it('blocks submit when the email is unchanged', async () => {
    const { result } = renderHookWithLocale(() =>
      useChangeEmail({ currentEmail: 'same@example.com' }),
    )

    act(() => {
      result.current.setEmail('same@example.com')
      result.current.setCurrentPassword(VALID_TEST_PASSWORD)
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(changeEmail).not.toHaveBeenCalled()
    expect(result.current.error).toContain('different email')
  })

  it('maps api errors to the form error state', async () => {
    vi.mocked(changeEmail).mockRejectedValue(
      new ApiError(422, 'The email has already been taken.', 'validation_error'),
    )

    const { result } = renderHookWithLocale(() =>
      useChangeEmail({ currentEmail: 'before@example.com' }),
    )

    act(() => {
      result.current.setEmail('taken@example.com')
      result.current.setCurrentPassword(VALID_TEST_PASSWORD)
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
