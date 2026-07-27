/**
 * @file Timesheet auth flows: login, forgot password, reset password, email verification.
 */

import type { FormEvent } from 'react'
import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  emailVerificationMessage,
  fetchAppConfig,
  getApiErrorMessage,
  isResetPasswordRoute,
  parseEmailVerificationCallback,
  parseResetPasswordParams,
  requestPasswordReset,
  resendVerificationEmail,
  resetPassword,
  shouldBlockUnverifiedUser,
  type User,
} from '@ellr/api-client'
import { useAuth } from '@ellr/ui'

type AuthScreen = 'login' | 'forgot-password' | 'reset-password'

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

/**
 * Timesheet authentication state including password recovery screens.
 * @returns Auth session, recovery UI state, and handlers.
 */
export function useTimesheetAuth() {
  const auth = useAuth()
  const [authScreen, setAuthScreen] = useState<AuthScreen>(() =>
    isResetPasswordRoute(window.location.pathname) ? 'reset-password' : 'login',
  )
  const [requireEmailVerification, setRequireEmailVerification] = useState(false)
  const [loginNotice, setLoginNotice] = useState<string | null>(null)
  const [forgotEmail, setForgotEmail] = useState('')
  const [forgotSubmitting, setForgotSubmitting] = useState(false)
  const [forgotError, setForgotError] = useState<string | null>(null)
  const [forgotSuccess, setForgotSuccess] = useState<string | null>(null)
  const [resetPasswordValue, setResetPasswordValue] = useState('')
  const [resetPasswordConfirmation, setResetPasswordConfirmation] = useState('')
  const [resetSubmitting, setResetSubmitting] = useState(false)
  const [resetError, setResetError] = useState<string | null>(null)
  const [resetSuccess, setResetSuccess] = useState<string | null>(null)
  const [verificationMessage, setVerificationMessage] = useState<string | null>(null)
  const [verificationMessageVariant, setVerificationMessageVariant] = useState<'success' | 'error' | null>(
    null,
  )
  const [verificationSending, setVerificationSending] = useState(false)

  const resetParams = useMemo(
    () => parseResetPasswordParams(window.location.search),
    [],
  )

  const resetLinkInvalid = authScreen === 'reset-password' && (!resetParams.token || !resetParams.email)

  useEffect(() => {
    void fetchAppConfig()
      .then((config) => {
        setRequireEmailVerification(config.require_email_verification)
      })
      .catch(() => {
        setRequireEmailVerification(false)
      })
  }, [])

  useEffect(() => {
    const callback = parseEmailVerificationCallback(window.location.search)

    if (callback.result === null) {
      return
    }

    setLoginNotice(emailVerificationMessage(callback))
    window.history.replaceState({}, '', window.location.pathname)
  }, [])

  const goToLogin = useCallback(() => {
    setAuthScreen('login')
    setForgotError(null)
    setForgotSuccess(null)
    setResetError(null)
    setResetSuccess(null)
    window.history.replaceState({}, '', resolveLoginPath(window.location.pathname))
  }, [])

  const handleLogin = async (event: FormEvent) => {
    setLoginNotice(null)
    await auth.handleLogin(event)
  }

  const handleForgotPassword = async (event: FormEvent) => {
    event.preventDefault()
    setForgotSubmitting(true)
    setForgotError(null)
    setForgotSuccess(null)

    try {
      await requestPasswordReset(forgotEmail)
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

  const handleResendVerification = async () => {
    setVerificationSending(true)
    setVerificationMessage(null)
    setVerificationMessageVariant(null)

    try {
      await resendVerificationEmail()
      setVerificationMessage('Verification link sent.')
      setVerificationMessageVariant('success')
    } catch (caught) {
      setVerificationMessage(getApiErrorMessage(caught, 'Unable to send the verification email.'))
      setVerificationMessageVariant('error')
    } finally {
      setVerificationSending(false)
    }
  }

  const showEmailVerification = (user: User | null): boolean => {
    return user !== null && shouldBlockUnverifiedUser(user, requireEmailVerification)
  }

  return {
    ...auth,
    handleLogin,
    authScreen,
    setAuthScreen,
    loginNotice,
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
    verificationMessage,
    verificationMessageVariant,
    verificationSending,
    goToLogin,
    handleForgotPassword,
    handleResetPassword,
    handleResendVerification,
    showEmailVerification,
  }
}
