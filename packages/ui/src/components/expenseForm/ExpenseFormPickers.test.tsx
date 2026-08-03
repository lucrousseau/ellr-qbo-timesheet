/**
 * @file Tests for expense form QBO picker wiring.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../i18n/LocaleProvider'
import { ExpenseFormPickers } from './ExpenseFormPickers'

const emptySelect = {
  items: [] as { id: string; display_name: string }[],
  loading: false,
  loaded: true,
  onOpen: vi.fn(),
  onClose: vi.fn(),
}

describe('ExpenseFormPickers', () => {
  it('renders account and party pickers', () => {
    render(
      <LocaleProvider>
        <ExpenseFormPickers
          paymentAccount={null}
          expenseAccount={null}
          vendor={null}
          customer={null}
          project={null}
          paymentAccountsSelect={emptySelect}
          expenseAccountsSelect={emptySelect}
          vendorsSelect={emptySelect}
          customersSelect={emptySelect}
          projectsSelect={emptySelect}
          onPaymentAccountChange={vi.fn()}
          onExpenseAccountChange={vi.fn()}
          onVendorChange={vi.fn()}
          onCustomerChange={vi.fn()}
          onProjectChange={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Payment account')).toBeInTheDocument()
    expect(screen.getByText('Expense account')).toBeInTheDocument()
    expect(screen.getByText('Vendor')).toBeInTheDocument()
    expect(screen.getByText('Customer')).toBeInTheDocument()
    expect(screen.getByText('Project')).toBeInTheDocument()
  })

  it('shows select-customer-first placeholder when no customer is chosen', () => {
    render(
      <LocaleProvider>
        <ExpenseFormPickers
          paymentAccount={null}
          expenseAccount={null}
          vendor={null}
          customer={null}
          project={null}
          paymentAccountsSelect={emptySelect}
          expenseAccountsSelect={emptySelect}
          vendorsSelect={emptySelect}
          customersSelect={emptySelect}
          projectsSelect={emptySelect}
          onPaymentAccountChange={vi.fn()}
          onExpenseAccountChange={vi.fn()}
          onVendorChange={vi.fn()}
          onCustomerChange={vi.fn()}
          onProjectChange={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Select a customer first.')).toBeInTheDocument()
  })
})
