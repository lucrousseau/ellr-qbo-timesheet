/**
 * @file Tests for combined locale and timezone preference persistence.
 */

import type { FormEvent } from 'react'
import { act } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { updateUserPreferences, type User } from '@ellr/api-client'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useUserPreferences } from './useUserPreferences'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    updateUserPreferences: vi.fn(),
  }
})

const baseUser: User = {
  id: 1,
  name: 'Jane',
  email: 'jane@example.com',
  locale: 'en',
  organization: {
    id: 1,
    name: 'Acme',
    slug: 'acme',
    qbo_connected: true,
    company_timezone: 'America/Toronto',
  },
}

describe('useUserPreferences', () => {
  beforeEach(() => {
    vi.mocked(updateUserPreferences).mockReset()
  })

  it('saves locale and timezone together', async () => {
    const updatedUser: User = { ...baseUser, locale: 'fr', timezone: 'Europe/Paris' }
    vi.mocked(updateUserPreferences).mockResolvedValue(updatedUser)
    const setUser = vi.fn()
    const onSaved = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useUserPreferences({
        user: baseUser,
        setUser,
        onSaved,
      }),
    )

    act(() => {
      result.current.setPreferenceLocale('fr')
      result.current.setPreferenceTimezone('Europe/Paris')
    })

    await act(async () => {
      await result.current.savePreferences({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(updateUserPreferences).toHaveBeenCalledWith({
      locale: 'fr',
      timezone: 'Europe/Paris',
    })
    expect(setUser).toHaveBeenCalledWith(updatedUser)
    expect(onSaved).toHaveBeenCalled()
  })

  it('stores null when the selected timezone matches the company timezone', async () => {
    vi.mocked(updateUserPreferences).mockResolvedValue(baseUser)
    const setUser = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useUserPreferences({
        user: baseUser,
        setUser,
      }),
    )

    act(() => {
      result.current.setPreferenceTimezone('America/Toronto')
    })

    await act(async () => {
      await result.current.savePreferences({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(updateUserPreferences).toHaveBeenCalledWith({
      locale: 'en',
      timezone: null,
    })
  })

  it('reports localized api errors through onError', async () => {
    vi.mocked(updateUserPreferences).mockRejectedValue(new Error('network'))
    const onError = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useUserPreferences({
        user: baseUser,
        setUser: vi.fn(),
        onError,
      }),
    )

    await act(async () => {
      await result.current.savePreferences({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(onError).toHaveBeenCalledWith('Unable to save preferences.')
  })
})
