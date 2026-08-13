/**
 * @file Supervisor panel for pending time entry approvals in the timesheet app.
 */

import { TimeEntryApprovalsPanel, useLocale } from '@ellr/ui'

type TimesheetTimeEntryApprovalsPanelProps = {
  enabled: boolean
  useAdminRoutes: boolean
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Lists pending direct-report time entries for supervisors in the timesheet app.
 * @param props Enable flag, route selection, and flash callbacks.
 * @returns Approval review panel for supervisors.
 */
export function TimesheetTimeEntryApprovalsPanel({
  enabled,
  useAdminRoutes,
  onSuccess,
  onError,
}: TimesheetTimeEntryApprovalsPanelProps) {
  const { t } = useLocale()

  return (
    <TimeEntryApprovalsPanel
      enabled={enabled}
      admin={useAdminRoutes}
      title={t('timesheet.timeApprovalsTitle')}
      help={t('timesheet.timeApprovalsHelp')}
      emptyMessage={t('timesheet.noPendingApprovals')}
      messages={{
        approvalFailed: t('timesheet.approvalFailed'),
        approvalSuccess: t('timesheet.approvalSuccess'),
        approvalSuccessQueued: t('timesheet.approvalSuccessQueued'),
        rejectionSuccess: t('timesheet.rejectionSuccess'),
        returnToDraftSuccess: t('timesheet.returnToDraftSuccess'),
      }}
      onSuccess={onSuccess}
      onError={onError}
    />
  )
}
