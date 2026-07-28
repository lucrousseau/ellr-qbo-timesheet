/**
 * @file Authenticated password change state and submit handler.
 */

import type { FormEvent } from 'react'
import { useState } from 'react'
import {
  ApiError,
  changePassword,
} from '@ellr/api-client'
import {
  loadPasswordPolicy,
  validatePasswordWithConfirmation,
} from '@ellr/password-policy'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { translatePasswordPolicyErrors, translateApiPasswordValidationMessage } from '../i18n/passwordPolicyMessages'

type UseChangePasswordOptions = {
  onSuccess?: () => void
  onError?: (message: string) => void
}

/**
 * State and handlers for changing the signed-in user password.
 * @param options Optional success and error callbacks.
 * @returns Controlled form state and submit handler.
 */
export function useChangePassword(options: UseChangePasswordOptions = {}) {
  const { locale, t } = useLocale()
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()

    const policy = loadPasswordPolicy()
    const validationErrors = validatePasswordWithConfirmation(
      password,
      passwordConfirmation,
      policy,
    )

    if (validationErrors.length > 0) {
      const message = translatePasswordPolicyErrors(locale, validationErrors)
      setError(message)
      setSuccess(null)
      options.onError?.(message)
      return
    }

    setSaving(true)
    setError(null)
    setSuccess(null)

    try {
      await changePassword(currentPassword, password, passwordConfirmation)
      setCurrentPassword('')
      setPassword('')
      setPasswordConfirmation('')
      const message = t('auth.passwordChanged')
      setSuccess(message)
      options.onSuccess?.()
    } catch (caught) {
      const apiMessage = caught instanceof ApiError ? caught.message : ''
      const policyMessage = translateApiPasswordValidationMessage(locale, apiMessage)
      const message = policyMessage ?? getApiErrorMessage(caught, t('auth.changePasswordFailed'), locale)
      setError(message)
      options.onError?.(message)
    } finally {
      setSaving(false)
    }
  }

  return {
    currentPassword,
    setCurrentPassword,
    password,
    setPassword,
    passwordConfirmation,
    setPasswordConfirmation,
    saving,
    error,
    success,
    handleSubmit,
  }
}
