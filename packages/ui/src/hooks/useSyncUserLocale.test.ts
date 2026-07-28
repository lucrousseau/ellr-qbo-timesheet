/**
 * @file Tests for syncing API user locale into LocaleProvider.
 */

import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { User } from '@ellr/api-client'
import { useLocale } from '../i18n/LocaleProvider'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { useSyncUserLocale } from './useSyncUserLocale'

describe('useSyncUserLocale', () => {
  it('applies the signed-in user locale to the provider', async () => {
    const user: User = {
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      locale: 'fr',
    }

    const { result } = renderHook(
      () => {
        useSyncUserLocale(user)
        return useLocale()
      },
      { wrapper: createLocaleWrapper('en') },
    )

    await waitFor(() => {
      expect(result.current.locale).toBe('fr')
      expect(result.current.t('timesheet.appTitle')).toBe('Feuille de temps')
    })
  })

  it('resets to english when the user signs out', async () => {
    const user: User = {
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      locale: 'fr',
    }

    const { result, rerender } = renderHook(
      ({ activeUser }: { activeUser: User | null }) => {
        useSyncUserLocale(activeUser)
        return useLocale()
      },
      {
        initialProps: { activeUser: user as User | null },
        wrapper: createLocaleWrapper('en'),
      },
    )

    await waitFor(() => {
      expect(result.current.locale).toBe('fr')
    })

    rerender({ activeUser: null })

    await waitFor(() => {
      expect(result.current.locale).toBe('en')
      expect(result.current.t('common.signIn')).toBe('Sign in')
    })
  })
})
