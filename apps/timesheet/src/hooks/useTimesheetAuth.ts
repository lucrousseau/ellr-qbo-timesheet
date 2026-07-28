/**
 * @file Timesheet auth flows: login, forgot password, reset password, email verification.
 */

import type { FormEvent } from 'react'
import { useEffect, useState } from 'react'
import {
  fetchAppConfig,
  getApiErrorMessage,
  parseEmailVerificationCallback,
  resendVerificationEmail,
  shouldBlockUnverifiedUser,
  type User,
} from '@ellr/api-client'
import { useAuth, useLocale, usePasswordRecovery, useUserLocalePreferences } from '@ellr/ui'

/**
 * Timesheet authentication state including password recovery screens.
 * @returns Auth session, recovery UI state, and handlers.
 */
export function useTimesheetAuth() {
  const { locale, t } = useLocale()
  const auth = useAuth({ bootstrapErrorFallback: t('admin.bootstrapFailed') })
  const recovery = usePasswordRecovery({ client: 'timesheet' })
  const [requireEmailVerification, setRequireEmailVerification] = useState(false)
  const [loginNotice, setLoginNotice] = useState<string | null>(null)
  const [verificationMessage, setVerificationMessage] = useState<string | null>(null)
  const [verificationMessageVariant, setVerificationMessageVariant] = useState<'success' | 'error' | null>(
    null,
  )
  const [verificationSending, setVerificationSending] = useState(false)
  const [preferenceNotice, setPreferenceNotice] = useState<string | null>(null)
  const [preferenceNoticeVariant, setPreferenceNoticeVariant] = useState<'success' | 'error' | null>(null)

  const preferences = useUserLocalePreferences({
    user: auth.user,
    setUser: auth.setUser,
    onSaved: () => {
      setPreferenceNotice(t('preferences.saved'))
      setPreferenceNoticeVariant('success')
    },
    onError: (message) => {
      setPreferenceNotice(message)
      setPreferenceNoticeVariant('error')
    },
  })

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

    if (callback.result === 'verified') {
      setLoginNotice(t('auth.verificationVerified'))
    } else if (callback.result === 'already_verified') {
      setLoginNotice(t('auth.verificationAlreadyVerified'))
    } else if (callback.result === 'error') {
      setLoginNotice(
        callback.reason === 'expired' ? t('auth.verificationExpired') : t('auth.verificationFailed'),
      )
    } else {
      setLoginNotice(t('auth.verificationGenericFailed'))
    }

    window.history.replaceState({}, '', window.location.pathname)
  }, [t])

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
      setVerificationMessage(t('auth.verificationSent'))
      setVerificationMessageVariant('success')
    } catch (caught) {
      setVerificationMessage(getApiErrorMessage(caught, t('auth.verificationSendFailed'), locale))
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
    ...preferences,
    handleLogin,
    loginNotice,
    verificationMessage,
    verificationMessageVariant,
    verificationSending,
    handleResendVerification,
    showEmailVerification,
    preferenceNotice,
    preferenceNoticeVariant,
    clearPreferenceNotice: () => {
      setPreferenceNotice(null)
      setPreferenceNoticeVariant(null)
    },
  }
}
