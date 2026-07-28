/**
 * @file Prompt to verify email and resend the verification link.
 */

import { Alert, Button, useLocale } from '@ellr/ui'

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
  const { t } = useLocale()

  return (
    <div className="mb-4 space-y-3">
      <Alert variant="warning">{t('timesheet.emailVerificationWarning')}</Alert>
      {message && messageVariant && <Alert variant={messageVariant}>{message}</Alert>}
      <Button type="button" disabled={sending} onClick={onResend}>
        {sending ? t('timesheet.resendingVerification') : t('timesheet.resendVerification')}
      </Button>
    </div>
  )
}
