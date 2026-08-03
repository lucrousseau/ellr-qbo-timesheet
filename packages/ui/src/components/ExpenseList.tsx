/**
 * @file List of the signed-in user's local expenses.
 */

import type { Expense, ExpenseStatus } from '@ellr/api-client'
import { Button } from './Button'
import { useLocale } from '../i18n/LocaleProvider'
import { cardClass } from '../styles/tokens'

type ExpenseListProps = {
  expenses: Expense[]
  loading?: boolean
  deletingId?: number | null
  onDelete?: (id: number) => void | Promise<void>
}

/**
 * Resolves a localized label for an expense approval status.
 * @param status Expense lifecycle status.
 * @param t Translator from useLocale.
 * @returns Localized status label.
 */
function statusLabel(status: ExpenseStatus, t: (key: string) => string): string {
  if (status === 'approved') {
    return t('expense.statusApproved')
  }

  if (status === 'rejected') {
    return t('expense.statusRejected')
  }

  return t('expense.statusPending')
}

/**
 * Renders the employee's expenses with delete actions for pending/rejected rows.
 * @param props Expense rows and optional delete handler.
 * @returns Expense list card.
 */
export function ExpenseList({
  expenses,
  loading = false,
  deletingId = null,
  onDelete,
}: ExpenseListProps) {
  const { t } = useLocale()

  return (
    <section className={cardClass}>
      <h2 className="text-lg font-medium text-slate-900">{t('expense.listTitle')}</h2>

      <div className="mt-4">
        {loading ? (
          <p className="text-sm text-slate-600">{t('common.loading')}</p>
        ) : expenses.length === 0 ? (
          <p className="text-sm text-slate-600">{t('expense.empty')}</p>
        ) : (
          <ul className="space-y-3">
            {expenses.map((expense) => {
              const canDelete =
                (expense.status === 'pending' || expense.status === 'rejected') && onDelete

              return (
                <li
                  key={expense.id}
                  className="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700"
                >
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                      <p className="font-medium text-slate-900">
                        {expense.amount}
                        {' · '}
                        {expense.txn_date}
                        {' · '}
                        {statusLabel(expense.status, t)}
                      </p>
                      <p>
                        {expense.payment_account_name ?? expense.payment_account_ref}
                        {' → '}
                        {expense.expense_account_name ?? expense.expense_account_ref}
                      </p>
                      {expense.vendor_name || expense.customer_name || expense.project_name ? (
                        <p>
                          {[expense.vendor_name, expense.customer_name, expense.project_name]
                            .filter(Boolean)
                            .join(' · ')}
                        </p>
                      ) : null}
                      {expense.description?.trim() ? <p>{expense.description}</p> : null}
                    </div>

                    {canDelete ? (
                      <Button
                        type="button"
                        variant="danger"
                        size="compact"
                        disabled={deletingId === expense.id}
                        onClick={() => void onDelete(expense.id)}
                      >
                        {deletingId === expense.id ? t('expense.deleting') : t('expense.delete')}
                      </Button>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </section>
  )
}
