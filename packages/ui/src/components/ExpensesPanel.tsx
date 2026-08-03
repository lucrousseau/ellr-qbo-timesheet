/**
 * @file Shared expenses workspace with optional approval review.
 */

import { useLocale } from '../i18n/LocaleProvider'
import { useExpenseApprovals } from '../hooks/useExpenseApprovals'
import { useExpenses } from '../hooks/useExpenses'
import { cardClass } from '../styles/tokens'
import { Alert } from './Alert'
import { Button } from './Button'
import { ExpenseApprovalList } from './ExpenseApprovalList'
import { ExpenseForm } from './ExpenseForm'
import { ExpenseList } from './ExpenseList'
import { LoadingScreen } from './LoadingScreen'

type ExpensesPanelProps = {
  enabled: boolean
  showApprovals?: boolean
  adminApprovals?: boolean
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Compose expense capture, list, and optional pending approvals.
 * @param props Enable flag, approval visibility, and flash callbacks.
 * @returns Expenses workspace sections.
 */
export function ExpensesPanel({
  enabled,
  showApprovals = false,
  adminApprovals = false,
  onSuccess,
  onError,
}: ExpensesPanelProps) {
  const { t } = useLocale()
  const expenses = useExpenses({ enabled, onSuccess, onError })
  const approvals = useExpenseApprovals({
    enabled: enabled && showApprovals,
    admin: adminApprovals,
    onSuccess,
    onError,
  })

  return (
    <div className="space-y-6">
      <ExpenseForm
        onSubmit={expenses.createExpense}
        onError={onError}
        onCreated={() => {
          void expenses.refresh()
        }}
      />

      {expenses.error ? <Alert variant="error">{expenses.error}</Alert> : null}

      <ExpenseList
        expenses={expenses.expenses}
        loading={expenses.loading}
        deletingId={expenses.deletingId}
        onDelete={expenses.deleteExpense}
      />

      {showApprovals ? (
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
                      {approvals.loadingMore
                        ? t('timeActivity.loadingMore')
                        : t('timeActivity.loadMore')}
                    </Button>
                  </div>
                ) : null}
              </>
            )}
          </div>
        </section>
      ) : null}
    </div>
  )
}
