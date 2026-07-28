/**
 * @file Confirmation dialog for revoking a provisioned timesheet user account.
 */

import { ConfirmDialog, useLocale } from '@ellr/ui'
import type { User } from '../hooks/useTimesheetProvisioning'

type RemoveTimesheetAccessDialogProps = {
  user: User | null
  confirming: boolean
  onConfirm: (userId: number) => Promise<boolean>
  onClose: () => void
}

/**
 * Warns that the app account is deleted while QuickBooks hours are preserved.
 * @param props Target user, pending state, and confirm/cancel handlers.
 * @returns Alert dialog or null when closed.
 */
export function RemoveTimesheetAccessDialog({
  user,
  confirming,
  onConfirm,
  onClose,
}: RemoveTimesheetAccessDialogProps) {
  const { t } = useLocale()

  return (
    <ConfirmDialog
      open={user !== null}
      title={t('admin.removeTimesheetAccessConfirmTitle')}
      description={t('admin.removeTimesheetAccessConfirmDescription')}
      confirmLabel={t('admin.removeTimesheetAccessConfirmAction')}
      cancelLabel={t('common.cancel')}
      confirming={confirming}
      onConfirm={async () => {
        if (user === null) {
          return
        }

        const removed = await onConfirm(user.id)
        if (removed) {
          onClose()
        }
      }}
      onCancel={onClose}
    />
  )
}
