/**
 * @file Email verification and password reset URL helpers for the timesheet UI.
 */

/**
 * Parsed email verification redirect from the API callback.
 */
export type EmailVerificationCallback = {
  result: 'verified' | 'already_verified' | 'error' | null
  reason?: string | null
}

/**
 * Parses email verification query parameters from the frontend URL.
 * @param search `location.search` string (e.g. `?email=verified`).
 * @returns Verification result and optional error reason.
 */
export function parseEmailVerificationCallback(search: string): EmailVerificationCallback {
  const params = new URLSearchParams(search)
  const emailResult = params.get('email')

  if (emailResult === 'verified') {
    return { result: 'verified' }
  }

  if (emailResult === 'already_verified') {
    return { result: 'already_verified' }
  }

  if (emailResult === 'error') {
    return { result: 'error', reason: params.get('reason') }
  }

  return { result: null }
}

/**
 * User-facing message for an email verification redirect.
 * @param callback Parsed callback from `parseEmailVerificationCallback`.
 * @returns English label for the timesheet UI.
 */
export function emailVerificationMessage(callback: EmailVerificationCallback): string {
  if (callback.result === 'verified') {
    return 'Email verified. You can sign in now.'
  }

  if (callback.result === 'already_verified') {
    return 'Email was already verified. You can sign in.'
  }

  if (callback.result === 'error') {
    if (callback.reason === 'expired') {
      return 'Verification link expired. Request a new one after signing in.'
    }

    return 'Unable to verify your email. Request a new link after signing in.'
  }

  return 'Unable to verify your email.'
}

/**
 * Returns whether the pathname targets the password reset screen.
 * @param pathname `location.pathname` value.
 * @returns `true` when the reset form should render.
 */
export function isResetPasswordRoute(pathname: string): boolean {
  return pathname === '/reset-password' || /\/reset-password\/?$/.test(pathname)
}

/**
 * Reads password reset token and email from the query string.
 * @param search `location.search` string.
 * @returns Token and email when present.
 */
export function parseResetPasswordParams(search: string): {
  token: string | null
  email: string | null
} {
  const params = new URLSearchParams(search)

  return {
    token: params.get('token'),
    email: params.get('email'),
  }
}

/**
 * Returns whether the user still needs to verify their email address.
 * @param user User object from the API.
 * @returns `true` when `email_verified_at` is empty.
 */
export function isEmailUnverified(user: { email_verified_at?: string | null }): boolean {
  return user.email_verified_at == null || user.email_verified_at === ''
}

/**
 * Returns whether the UI should block unverified users from protected features.
 * @param user User object from the API.
 * @param requireEmailVerification Server flag from `/api/health`.
 * @returns `true` when verification is required and still pending.
 */
export function shouldBlockUnverifiedUser(
  user: { email_verified_at?: string | null },
  requireEmailVerification: boolean,
): boolean {
  return requireEmailVerification && isEmailUnverified(user)
}
