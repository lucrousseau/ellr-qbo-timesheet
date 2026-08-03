/**
 * @file Card list for reviewing and approving pending expenses.
 */

import type { Expense } from '@ellr/api-client'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'
import { useLocale } from '../i18n/LocaleProvider'

type ExpenseApprovalListProps = {
  expenses: Expense[]
  reviewingId: number | null
  onApprove: (id: number) => Promise<void>
  onReject: (id: number, reason?: string | null) => Promise<void>
}

/**
 * Renders pending expenses as review cards with approve and reject actions.
 * @param props Pending expenses and review handlers.
 * @returns Approval card list.
 */
export function ExpenseApprovalList({
  expenses,
  reviewingId,
  onApprove,
  onReject,
}: ExpenseApprovalListProps) {
  const { t } = useLocale()

  return (
    <ul className="space-y-4">
      {expenses.map((expense) => {
        const entryId = String(expense.id)

        return (
          <li
            key={expense.id}
            className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
          >
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div className="min-w-0 space-y-2 text-sm text-slate-700">
                <p className="text-base font-medium text-slate-900">
                  {expense.employee_name ?? t('timeActivity.noValue')}
                  {' · '}
                  {expense.amount}
                  {' · '}
                  {expense.txn_date}
                </p>
                <p>
                  {expense.payment_account_name ?? expense.payment_account_ref}
                  {' → '}
                  {expense.expense_account_name ?? expense.expense_account_ref}
                </p>
                <p>
                  {expense.vendor_name ?? t('timeActivity.noValue')}
                  {' · '}
                  {expense.customer_name ?? t('timeActivity.noValue')}
                  {' · '}
                  {expense.project_name ?? t('timeActivity.noValue')}
                </p>
                {expense.description?.trim() ? <p>{expense.description}</p> : null}
                <p className="text-slate-500">
                  {expense.is_billable ? t('timeActivity.billableYes') : t('timeActivity.billableNo')}
                </p>
              </div>

              <TimeEntryApprovalActions
                entryId={entryId}
                reviewing={reviewingId === expense.id}
                onApprove={async (id) => onApprove(Number(id))}
                onReject={async (id, reason) => onReject(Number(id), reason)}
              />
            </div>
          </li>
        )
      })}
    </ul>
  )
}
