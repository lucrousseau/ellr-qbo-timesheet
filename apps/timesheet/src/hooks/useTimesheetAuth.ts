/**
 * @file Timesheet auth flows: login, forgot password, reset password, email verification.
 */

import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import {
  emailVerificationMessage,
  fetchAppConfig,
  getApiErrorMessage,
  parseEmailVerificationCallback,
  resendVerificationEmail,
  shouldBlockUnverifiedUser,
  type User,
} from '@ellr/api-client'
import { useAuth, usePasswordRecovery } from '@ellr/ui'

/**
 * Timesheet authentication state including password recovery screens.
 * @returns Auth session, recovery UI state, and handlers.
 */
export function useTimesheetAuth() {
  const auth = useAuth()
  const recovery = usePasswordRecovery({ client: 'timesheet' })
  const [requireEmailVerification, setRequireEmailVerification] = useState(false)
  const [loginNotice, setLoginNotice] = useState<string | null>(null)
  const [verificationMessage, setVerificationMessage] = useState<string | null>(null)
  const [verificationMessageVariant, setVerificationMessageVariant] = useState<'success' | 'error' | null>(
    null,
  )
  const [verificationSending, setVerificationSending] = useState(false)

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

  const handleLogin = async (event: FormEvent) => {
    setLoginNotice(null)
    await auth.handleLogin(event)
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
    ...recovery,
    handleLogin,
    loginNotice,
    verificationMessage,
    verificationMessageVariant,
    verificationSending,
    handleResendVerification,
    showEmailVerification,
  }
}
