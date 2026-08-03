/**
 * @file Tests for the pending expense approval list.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Expense } from '@ellr/api-client'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { ExpenseApprovalList } from './ExpenseApprovalList'

const pendingExpense: Expense = {
  id: 12,
  user_id: 2,
  employee_name: 'Bob LeMoche',
  amount: '25.00',
  txn_date: '2026-08-01',
  payment_type: 'Cash',
  payment_account_ref: '1',
  payment_account_name: 'Checking',
  expense_account_ref: '2',
  expense_account_name: 'Travel',
  description: 'Client lunch',
  is_billable: true,
  status: 'pending',
}

describe('ExpenseApprovalList', () => {
  it('renders approve and reject actions for each pending expense', async () => {
    const user = userEvent.setup()
    const onApprove = vi.fn().mockResolvedValue(undefined)
    const onReject = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <ExpenseApprovalList
          expenses={[pendingExpense]}
          reviewingId={null}
          onApprove={onApprove}
          onReject={onReject}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText(/Bob LeMoche/)).toBeInTheDocument()
    expect(screen.getByText(/Client lunch/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Approve' }))
    expect(onApprove).toHaveBeenCalledWith(12)
  })
})
