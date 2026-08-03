/**
 * @file Supervisor panel for pending expense approvals in the timesheet app.
 */

import {
  Alert,
  Button,
  ExpenseApprovalList,
  LoadingScreen,
  cardClass,
  useExpenseApprovals,
  useLocale,
} from '@ellr/ui'

type TimesheetExpenseApprovalsPanelProps = {
  enabled: boolean
  useAdminRoutes: boolean
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Lists pending direct-report expenses for supervisors in the timesheet app.
 * @param props Enable flag, route selection, and flash callbacks.
 * @returns Expense approval review panel.
 */
export function TimesheetExpenseApprovalsPanel({
  enabled,
  useAdminRoutes,
  onSuccess,
  onError,
}: TimesheetExpenseApprovalsPanelProps) {
  const { t } = useLocale()
  const approvals = useExpenseApprovals({
    enabled,
    admin: useAdminRoutes,
    onSuccess,
    onError,
  })

  return (
    <section className={cardClass}>
      <h2 className="text-lg font-medium text-slate-900">{t('expense.approvalsTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('expense.approvalsHelp')}</p>

      <div className="mt-4">
        {approvals.error ? <Alert variant="error">{approvals.error}</Alert> : null}

        {approvals.loading ? (
          <LoadingScreen />
        ) : approvals.expenses.length === 0 ? (
          <p className="text-sm text-slate-600">{t('expense.noPendingApprovals')}</p>
        ) : (
          <>
            <ExpenseApprovalList
              expenses={approvals.expenses}
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
