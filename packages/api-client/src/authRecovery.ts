/**
 * @file Email verification and password reset URL helpers for admin and timesheet UIs.
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
 * Returns whether the pathname targets the password reset screen.
 * @param pathname `location.pathname` value.
 * @returns `true` when the reset form should render.
 */
export function isResetPasswordRoute(pathname: string): boolean {
  return pathname === '/reset-password' || /\/reset-password\/?$/.test(pathname)
}

/**
 * Returns whether the current URL contains a complete password reset invite.
 * @returns `true` when token and email query params are both present.
 */
export function hasValidPasswordResetInvite(): boolean {
  if (!isResetPasswordRoute(window.location.pathname)) {
    return false
  }

  const { token, email } = parseResetPasswordParams(window.location.search)

  return Boolean(token && email)
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
