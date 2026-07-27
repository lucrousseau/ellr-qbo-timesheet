/**
 * @file Prompt to verify email and resend the verification link.
 */

import { Alert, primaryButtonClass } from '@ellr/ui'

type EmailVerificationBannerProps = {
  message: string | null
  messageVariant?: 'success' | 'error' | null
  sending: boolean
  onResend: () => void
}

/**
 * Warns unverified users and offers to resend the verification email.
 * @param props Banner copy and resend handler.
 * @returns Verification notice block.
 */
export function EmailVerificationBanner({
  message,
  messageVariant = null,
  sending,
  onResend,
}: EmailVerificationBannerProps) {
  return (
    <div className="mb-4 space-y-3">
      <Alert variant="warning">
        Verify your email address to record time. Check your inbox for the verification link.
      </Alert>
      {message && messageVariant && <Alert variant={messageVariant}>{message}</Alert>}
      <button type="button" className={primaryButtonClass} disabled={sending} onClick={onResend}>
        {sending ? 'Sending…' : 'Resend verification email'}
      </button>
    </div>
  )
}
