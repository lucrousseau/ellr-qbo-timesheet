/**
 * @file Administrator panel for pending time entry approvals.
 */

import { Alert, Button, LoadingScreen, TimeEntryApprovalList, cardClass, sectionHelpClass, sectionTitleCompactClass, useLocale, useTimeEntryApprovals } from '@ellr/ui'

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
  const approvals = useTimeEntryApprovals({ enabled, admin: true, onSuccess, onError })

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleCompactClass}>{t('admin.timeApprovalsTitle')}</h2>
      <p className={sectionHelpClass}>{t('admin.timeApprovalsHelp')}</p>

      <div className="mt-4">
        {approvals.error ? (
          <Alert variant="error">{approvals.error}</Alert>
        ) : null}

        {approvals.loading ? (
          <LoadingScreen variant="inline" />
        ) : approvals.entries.length === 0 ? (
          <p className="text-sm text-brand-muted">{t('admin.noPendingApprovals')}</p>
        ) : (
          <>
            <TimeEntryApprovalList
              entries={approvals.entries}
              reviewingId={approvals.reviewingId}
              onApprove={approvals.approveEntry}
              onReject={approvals.rejectEntry}
              onUpdateEntry={approvals.updateEntry}
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
