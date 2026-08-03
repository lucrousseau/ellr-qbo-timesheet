/**
 * @file Tests for the shared expenses workspace panel.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider, useExpenseApprovals, useExpenses } from '../index'
import { ExpensesPanel } from './ExpensesPanel'

vi.mock('../hooks/useExpenses', () => ({
  useExpenses: vi.fn(),
}))

vi.mock('../hooks/useExpenseApprovals', () => ({
  useExpenseApprovals: vi.fn(),
}))

vi.mock('../hooks/useExpenseForm', () => ({
  useExpenseForm: vi.fn(() => ({
    amount: '',
    setAmount: vi.fn(),
    txnDate: '2026-08-03',
    setTxnDate: vi.fn(),
    paymentType: null,
    setPaymentType: vi.fn(),
    paymentTypes: [],
    paymentAccount: null,
    setPaymentAccount: vi.fn(),
    expenseAccount: null,
    setExpenseAccount: vi.fn(),
    vendor: null,
    setVendor: vi.fn(),
    customer: null,
    project: null,
    description: '',
    setDescription: vi.fn(),
    isBillable: false,
    setIsBillable: vi.fn(),
    paymentAccountsSelect: { items: [], loading: false, loaded: false, onOpen: vi.fn(), onClose: vi.fn() },
    expenseAccountsSelect: { items: [], loading: false, loaded: false, onOpen: vi.fn(), onClose: vi.fn() },
    vendorsSelect: { items: [], loading: false, loaded: false, onOpen: vi.fn(), onClose: vi.fn() },
    customersSelect: { items: [], loading: false, loaded: false, onOpen: vi.fn(), onClose: vi.fn() },
    projectsSelect: { items: [], loading: false, loaded: false, onOpen: vi.fn(), onClose: vi.fn() },
    onCustomerChange: vi.fn(),
    setProject: vi.fn(),
    handleSubmit: vi.fn(),
    submitting: false,
    canSubmit: false,
  })),
}))

describe('ExpensesPanel', () => {
  it('renders form, list, and approvals when enabled', () => {
    vi.mocked(useExpenses).mockReturnValue({
      expenses: [],
      loading: false,
      error: null,
      creating: false,
      deletingId: null,
      refresh: vi.fn(),
      createExpense: vi.fn(),
      deleteExpense: vi.fn(),
    })
    vi.mocked(useExpenseApprovals).mockReturnValue({
      expenses: [],
      loading: false,
      loadingMore: false,
      hasMore: false,
      error: null,
      reviewingId: null,
      loadMore: vi.fn(),
      approveEntry: vi.fn(),
      rejectEntry: vi.fn(),
    })

    render(
      <LocaleProvider>
        <ExpensesPanel
          enabled
          showApprovals
          adminApprovals
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Record an expense')).toBeInTheDocument()
    expect(screen.getByText('Your expenses')).toBeInTheDocument()
    expect(screen.getByText('Pending expense approvals')).toBeInTheDocument()
    expect(screen.getByText('No pending expenses to review.')).toBeInTheDocument()
  })
})
