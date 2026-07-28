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
  const [savingLocale, setSavingLocale] = useState(false)

  useEffect(() => {
    if (user) {
      setPreferenceLocale(normalizeUserLocale(user.locale))
    }
  }, [user])

  const saveLocale = async (event: FormEvent) => {
    event.preventDefault()
    setSavingLocale(true)

    try {
      const updatedUser = await updateUserLocale(preferenceLocale)
      setUser(updatedUser)
      setLocale(preferenceLocale)
      onSaved?.()
    } catch (caught) {
      onError?.(getApiErrorMessage(caught, t('preferences.saveFailed'), locale))
    } finally {
      setSavingLocale(false)
    }
  }

  return {
    preferenceLocale,
    setPreferenceLocale,
    savingLocale,
    saveLocale,
  }
}
