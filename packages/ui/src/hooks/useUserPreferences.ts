/**
 * @file Persists signed-in user language and timezone preferences via the API.
 */

import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import {
  normalizeUserLocale,
  normalizeUserTimezone,
  timezonePreferenceForApi,
  updateUserPreferences,
  type User,
  type UserLocale,
} from '@ellr/api-client'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

type UseUserPreferencesOptions = {
  user: User | null
  setUser: (user: User) => void
  onSaved?: () => void
  onError?: (message: string) => void
}

/**
 * Controlled locale and timezone preference state with a combined save handler.
 * @param options.user Current authenticated user.
 * @param options.setUser Updates session user after a successful save.
 * @param options.onSaved Optional success callback.
 * @param options.onError Optional error callback with a localized message.
 * @returns Preference form state and save handler.
 */
export function useUserPreferences({
  user,
  setUser,
  onSaved,
  onError,
}: UseUserPreferencesOptions) {
  const { locale, setLocale, t } = useLocale()
  const [preferenceLocale, setPreferenceLocale] = useState<UserLocale>('en')
  const [preferenceTimezone, setPreferenceTimezone] = useState('UTC')
  const companyTimezone = user?.organization?.company_timezone ?? null

  useEffect(() => {
    if (user) {
      setPreferenceLocale(normalizeUserLocale(user.locale))
      setPreferenceTimezone(normalizeUserTimezone(user.timezone, user.organization?.company_timezone))
    }
  }, [user])

  const { run: savePreferences, pending: savingPreferences } = useGuardedAction(
    async (event: FormEvent) => {
      event.preventDefault()

      try {
        const updatedUser = await updateUserPreferences({
          locale: preferenceLocale,
          timezone: timezonePreferenceForApi(preferenceTimezone, companyTimezone),
        })
        setUser(updatedUser)
        setLocale(preferenceLocale)
        onSaved?.()
      } catch (caught) {
        onError?.(getApiErrorMessage(caught, t('preferences.saveFailed'), locale))
      }
    },
  )

  return {
    preferenceLocale,
    setPreferenceLocale,
    preferenceTimezone,
    setPreferenceTimezone,
    companyTimezone,
    savingPreferences,
    savePreferences,
  }
}
