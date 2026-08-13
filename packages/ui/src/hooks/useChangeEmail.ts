/**
 * @file Authenticated email change state and submit handler.
 */

import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import { changeEmail, type User } from '@ellr/api-client'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

type UseChangeEmailOptions = {
  currentEmail: string
  onSuccess?: (user: User) => void
  onError?: (message: string) => void
}

/**
 * State and handlers for changing the signed-in user email.
 * @param options Current email and optional success/error callbacks.
 * @returns Controlled form state and submit handler.
 */
export function useChangeEmail(options: UseChangeEmailOptions) {
  const { locale, t } = useLocale()
  const [email, setEmail] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  useEffect(() => {
    setEmail('')
    setCurrentPassword('')
  }, [options.currentEmail])

  const { run: handleSubmit, pending: saving } = useGuardedAction(async (event: FormEvent) => {
    event.preventDefault()

    const nextEmail = email.trim()
    if (nextEmail === '' || nextEmail.toLowerCase() === options.currentEmail.toLowerCase()) {
      const message = t('auth.changeEmailUnchanged')
      setError(message)
      setSuccess(null)
      options.onError?.(message)
      return
    }

    setError(null)
    setSuccess(null)

    try {
      const user = await changeEmail(currentPassword, nextEmail)
      setCurrentPassword('')
      setEmail('')
      const message = t('auth.emailChanged')
      setSuccess(message)
      options.onSuccess?.(user)
    } catch (caught) {
      const message = getApiErrorMessage(caught, t('auth.changeEmailFailed'), locale)
      setError(message)
      options.onError?.(message)
    }
  })

  return {
    email,
    setEmail,
    currentPassword,
    setCurrentPassword,
    saving,
    error,
    success,
    handleSubmit,
  }
}
