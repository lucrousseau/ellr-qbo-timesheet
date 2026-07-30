/**
 * @file Supervisor panel for pending time entry approvals in the timesheet app.
 */

import { Alert, Button, LoadingScreen, TimeEntryApprovalList, cardClass, useLocale, useTimeEntryApprovals } from '@ellr/ui'

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
  const approvals = useTimeEntryApprovals({
    enabled,
    admin: useAdminRoutes,
    messages: {
      approvalFailed: t('timesheet.approvalFailed'),
      approvalSuccess: t('timesheet.approvalSuccess'),
      rejectionSuccess: t('timesheet.rejectionSuccess'),
    },
    onSuccess,
    onError,
  })

  return (
    <section className={cardClass}>
      <h2 className="text-lg font-medium text-slate-900">{t('timesheet.timeApprovalsTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('timesheet.timeApprovalsHelp')}</p>

      <div className="mt-4">
        {approvals.error ? <Alert variant="error">{approvals.error}</Alert> : null}

        {approvals.loading ? (
          <LoadingScreen />
        ) : approvals.entries.length === 0 ? (
          <p className="text-sm text-slate-600">{t('timesheet.noPendingApprovals')}</p>
        ) : (
          <>
            <TimeEntryApprovalList
              entries={approvals.entries}
              reviewingId={approvals.reviewingId}
              onApprove={approvals.approveEntry}
              onReject={approvals.rejectEntry}
            />

            {approvals.hasMore ? (
              <div className="mt-4">
                <Button
                  type="button"
                  variant="secondary"
                  disabled={approvals.loadingMore}
                  onClick={approvals.loadMore}
                >
                  {approvals.loadingMore ? t('timeActivity.loadingMore') : t('timeActivity.loadMore')}
                </Button>
              </div>
            ) : null}
          </>
        )}
      </div>
    </section>
  )
}
