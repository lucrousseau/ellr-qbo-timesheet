/**
 * @file Syncs the authenticated user locale preference into the UI provider.
 */

import { useEffect } from 'react'
import { normalizeUserLocale, type User } from '@ellr/api-client'
import { useLocale } from '../i18n/LocaleProvider'

/**
 * Applies the signed-in user locale to the shared LocaleProvider.
 * @param user Current authenticated user, or null when signed out.
 */
export function useSyncUserLocale(user: User | null): void {
  const { setLocale } = useLocale()

  useEffect(() => {
    if (user) {
      setLocale(normalizeUserLocale(user.locale))
      return
    }

    setLocale('en')
  }, [setLocale, user])
}
