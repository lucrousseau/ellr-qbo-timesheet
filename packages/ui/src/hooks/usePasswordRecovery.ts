/**
 * @file Password recovery screens: forgot password and reset from email link.
 */

import type { FormEvent } from 'react'
import { useCallback, useState } from 'react'
import {
  ApiError,
  getApiErrorMessage,
  isResetPasswordRoute,
  parseResetPasswordParams,
  requestPasswordReset,
  resetPassword,
} from '@ellr/api-client'
import {
  loadPasswordPolicy,
  validatePasswordWithConfirmation,
} from '@ellr/password-policy'
import { useLocale } from '../i18n/LocaleProvider'
import { translatePasswordPolicyErrors, translateApiPasswordValidationMessage } from '../i18n/passwordPolicyMessages'

/**
 * Frontend app that initiates password recovery.
 */
export type AuthClient = 'admin' | 'timesheet'

/**
 * Guest authentication screen identifier.
 */
export type AuthScreen = 'login' | 'forgot-password' | 'reset-password'

/**
 * Resolves the login path when leaving the reset-password screen.
 * @param pathname Current `location.pathname`.
 * @returns Base path for the sign-in screen.
 */
function resolveLoginPath(pathname: string): string {
  if (pathname === '/reset-password') {
    return '/'
  }

  if (pathname.endsWith('/reset-password')) {
    return pathname.slice(0, -'/reset-password'.length) || '/'
  }

  return '/'
}

type UsePasswordRecoveryOptions = {
  client: AuthClient
}

/**
 * State and handlers for forgot-password and reset-password guest screens.
 * @param options Frontend client used for reset email deep links.
 * @returns Recovery UI state and submit handlers.
 */
export function usePasswordRecovery(options: UsePasswordRecoveryOptions) {
  const { locale, t } = useLocale()
  const [authScreen, setAuthScreen] = useState<AuthScreen>(() =>
    isResetPasswordRoute(window.location.pathname) ? 'reset-password' : 'login',
  )
  const [forgotEmail, setForgotEmail] = useState('')
  const [forgotSubmitting, setForgotSubmitting] = useState(false)
  const [forgotError, setForgotError] = useState<string | null>(null)
  const [forgotSuccess, setForgotSuccess] = useState<string | null>(null)
  const [resetPasswordValue, setResetPasswordValue] = useState('')
  const [resetPasswordConfirmation, setResetPasswordConfirmation] = useState('')
  const [resetSubmitting, setResetSubmitting] = useState(false)
  const [resetError, setResetError] = useState<string | null>(null)
  const [resetSuccess, setResetSuccess] = useState<string | null>(null)

  const resetParams =
    authScreen === 'reset-password'
      ? parseResetPasswordParams(window.location.search)
      : { token: null, email: null }

  const resetLinkInvalid =
    authScreen === 'reset-password' && (!resetParams.token || !resetParams.email)

  const goToLogin = useCallback(() => {
    setAuthScreen('login')
    setForgotError(null)
    setForgotSuccess(null)
    setResetError(null)
    setResetSuccess(null)
    window.history.replaceState({}, '', resolveLoginPath(window.location.pathname))
  }, [])

  const handleForgotPassword = async (event: FormEvent) => {
    event.preventDefault()
    setForgotSubmitting(true)
    setForgotError(null)
    setForgotSuccess(null)

    try {
      await requestPasswordReset(forgotEmail, { client: options.client })
      setForgotSuccess(t('auth.resetLinkSent'))
    } catch (caught) {
      setForgotError(getApiErrorMessage(caught, t('auth.resetLinkSendFailed'), locale))
    } finally {
      setForgotSubmitting(false)
    }
  }

  const handleResetPassword = async (event: FormEvent) => {
    event.preventDefault()

    if (!resetParams.token || !resetParams.email) {
      return
    }

    const policy = loadPasswordPolicy()
    const validationErrors = validatePasswordWithConfirmation(
      resetPasswordValue,
      resetPasswordConfirmation,
      policy,
    )

    if (validationErrors.length > 0) {
      setResetError(translatePasswordPolicyErrors(locale, validationErrors))
      return
    }

    setResetSubmitting(true)
    setResetError(null)

    try {
      await resetPassword({
        token: resetParams.token,
        email: resetParams.email,
        password: resetPasswordValue,
        passwordConfirmation: resetPasswordConfirmation,
      })
      setResetSuccess(t('auth.passwordUpdated'))
      setResetPasswordValue('')
      setResetPasswordConfirmation('')
    } catch (caught) {
      const apiMessage = caught instanceof ApiError ? caught.message : ''
      const policyMessage = translateApiPasswordValidationMessage(locale, apiMessage)
      setResetError(
        policyMessage ?? getApiErrorMessage(caught, t('auth.resetFailed'), locale),
      )
    } finally {
      setResetSubmitting(false)
    }
  }

  return {
    authScreen,
    setAuthScreen,
    forgotEmail,
    setForgotEmail,
    forgotSubmitting,
    forgotError,
    forgotSuccess,
    resetParams,
    resetLinkInvalid,
    resetPasswordValue,
    setResetPasswordValue,
    resetPasswordConfirmation,
    setResetPasswordConfirmation,
    resetSubmitting,
    resetError,
    resetSuccess,
    goToLogin,
    handleForgotPassword,
    handleResetPassword,
  }
}
