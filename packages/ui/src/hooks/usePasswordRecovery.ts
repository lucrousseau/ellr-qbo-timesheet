/**
 * @file Password recovery screens: forgot password and reset from email link.
 */

import type { FormEvent } from 'react'
import { useCallback, useState } from 'react'
import {
  getApiErrorMessage,
  isResetPasswordRoute,
  parseResetPasswordParams,
  requestPasswordReset,
  resetPassword,
} from '@ellr/api-client'

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
      setForgotSuccess('If that email exists, a reset link has been sent.')
    } catch (caught) {
      setForgotError(getApiErrorMessage(caught, 'Unable to send the reset link.'))
    } finally {
      setForgotSubmitting(false)
    }
  }

  const handleResetPassword = async (event: FormEvent) => {
    event.preventDefault()

    if (!resetParams.token || !resetParams.email) {
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
      setResetSuccess('Password updated. You can sign in now.')
      setResetPasswordValue('')
      setResetPasswordConfirmation('')
    } catch (caught) {
      setResetError(getApiErrorMessage(caught, 'Unable to reset the password.'))
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
