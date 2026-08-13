/**
 * @file Administrator panel for pending time entry approvals.
 */

import { TimeEntryApprovalsPanel as SharedTimeEntryApprovalsPanel, useLocale } from '@ellr/ui'

type TimeEntryApprovalsPanelProps = {
  enabled: boolean
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Lists pending employee time entries and exposes approve/reject actions.
 * @param props Enable flag and flash message callbacks.
 * @returns Approval review panel for administrators.
 */
export function TimeEntryApprovalsPanel({ enabled, onSuccess, onError }: TimeEntryApprovalsPanelProps) {
  const { t } = useLocale()

  return (
    <SharedTimeEntryApprovalsPanel
      enabled={enabled}
      admin
      title={t('admin.timeApprovalsTitle')}
      help={t('admin.timeApprovalsHelp')}
      emptyMessage={t('admin.noPendingApprovals')}
      onSuccess={onSuccess}
      onError={onError}
    />
  )
}
