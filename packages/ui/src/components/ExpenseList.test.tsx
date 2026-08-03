/**
 * @file Tests for the employee expense list.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Expense } from '@ellr/api-client'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { ExpenseList } from './ExpenseList'

const pendingExpense: Expense = {
  id: 5,
  user_id: 2,
  amount: '18.00',
  txn_date: '2026-08-02',
  payment_type: 'Cash',
  payment_account_ref: '10',
  payment_account_name: 'Checking',
  expense_account_ref: '20',
  expense_account_name: 'Office Supplies',
  description: 'Printer paper',
  is_billable: false,
  status: 'pending',
}

describe('ExpenseList', () => {
  it('renders expenses and allows deleting pending rows', async () => {
    const user = userEvent.setup()
    const onDelete = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <ExpenseList expenses={[pendingExpense]} onDelete={onDelete} />
      </LocaleProvider>,
    )

    expect(screen.getByText(/18\.00/)).toBeInTheDocument()
    expect(screen.getByText(/Printer paper/)).toBeInTheDocument()
    expect(screen.getByText(/Checking/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Delete' }))
    expect(onDelete).toHaveBeenCalledWith(5)
  })

  it('shows an empty state when there are no expenses', () => {
    render(
      <LocaleProvider>
        <ExpenseList expenses={[]} />
      </LocaleProvider>,
    )

    expect(screen.getByText('No expenses yet.')).toBeInTheDocument()
  })

  it('hides delete for approved expenses', () => {
    render(
      <LocaleProvider>
        <ExpenseList
          expenses={[{ ...pendingExpense, status: 'approved', qbo_id: '99' }]}
          onDelete={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })
})
