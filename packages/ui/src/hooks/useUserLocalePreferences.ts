/**
 * @file Persists the signed-in user language preference via the API.
 */

import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import {
  normalizeUserLocale,
  updateUserLocale,
  type User,
  type UserLocale,
} from '@ellr/api-client'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

type UseUserLocalePreferencesOptions = {
  user: User | null
  setUser: (user: User) => void
  onSaved?: () => void
  onError?: (message: string) => void
}

/**
 * Controlled locale preference state with save handler for preferences panels.
 * @param options.user Current authenticated user.
 * @param options.setUser Updates session user after a successful save.
 * @param options.onSaved Optional success callback.
 * @param options.onError Optional error callback with a localized message.
 * @returns Preference form state and save handler.
 */
export function useUserLocalePreferences({
  user,
  setUser,
  onSaved,
  onError,
}: UseUserLocalePreferencesOptions) {
  const { locale, setLocale, t } = useLocale()
  const [preferenceLocale, setPreferenceLocale] = useState<UserLocale>('en')

  useEffect(() => {
    if (user) {
      setPreferenceLocale(normalizeUserLocale(user.locale))
    }
  }, [user])

  const { run: saveLocale, pending: savingLocale } = useGuardedAction(async (event: FormEvent) => {
    event.preventDefault()

    try {
      const updatedUser = await updateUserLocale(preferenceLocale)
      setUser(updatedUser)
      setLocale(preferenceLocale)
      onSaved?.()
    } catch (caught) {
      onError?.(getApiErrorMessage(caught, t('preferences.saveFailed'), locale))
    }
  })

  return {
    preferenceLocale,
    setPreferenceLocale,
    savingLocale,
    saveLocale,
  }
}
