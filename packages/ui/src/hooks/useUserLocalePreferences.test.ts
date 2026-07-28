/**
 * @file Tests for locale preference persistence hook.
 */

import type { FormEvent } from 'react'
import { act } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { updateUserLocale, type User } from '@ellr/api-client'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useUserLocalePreferences } from './useUserLocalePreferences'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    updateUserLocale: vi.fn(),
  }
})

const baseUser: User = {
  id: 1,
  name: 'Jane',
  email: 'jane@example.com',
  locale: 'en',
}

describe('useUserLocalePreferences', () => {
  beforeEach(() => {
    vi.mocked(updateUserLocale).mockReset()
  })

  it('saves the selected locale and updates the session user', async () => {
    const updatedUser: User = { ...baseUser, locale: 'fr' }
    vi.mocked(updateUserLocale).mockResolvedValue(updatedUser)
    const setUser = vi.fn()
    const onSaved = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useUserLocalePreferences({
        user: baseUser,
        setUser,
        onSaved,
      }),
    )

    act(() => {
      result.current.setPreferenceLocale('fr')
    })

    await act(async () => {
      await result.current.saveLocale({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(updateUserLocale).toHaveBeenCalledWith('fr')
    expect(setUser).toHaveBeenCalledWith(updatedUser)
    expect(onSaved).toHaveBeenCalled()
    expect(result.current.savingLocale).toBe(false)
  })

  it('reports localized api errors through onError', async () => {
    vi.mocked(updateUserLocale).mockRejectedValue(new Error('network'))
    const onError = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useUserLocalePreferences({
        user: baseUser,
        setUser: vi.fn(),
        onError,
      }),
    )

    await act(async () => {
      await result.current.saveLocale({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(onError).toHaveBeenCalledWith('Unable to save preferences.')
  })
})
