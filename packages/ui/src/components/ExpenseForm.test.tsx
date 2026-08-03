/**
 * @file Tests for the expense capture form.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { useExpenseForm } from '../hooks/useExpenseForm'
import { ExpenseForm } from './ExpenseForm'

vi.mock('../hooks/useExpenseForm', () => ({
  useExpenseForm: vi.fn(),
}))

const emptySelect = {
  items: [],
  loading: false,
  loaded: false,
  onOpen: vi.fn(),
  onClose: vi.fn(),
}

/**
 * Builds a complete mocked expense form state.
 * @param overrides Partial form state overrides.
 * @returns Mock useExpenseForm return value.
 */
function mockFormState(overrides: Record<string, unknown> = {}) {
  return {
    amount: '12.34',
    setAmount: vi.fn(),
    txnDate: '2026-08-03',
    setTxnDate: vi.fn(),
    paymentType: { value: 'Cash', label: 'Cash' },
    setPaymentType: vi.fn(),
    paymentTypes: [{ value: 'Cash', label: 'Cash' }],
    paymentAccount: { id: '1', display_name: 'Checking' },
    setPaymentAccount: vi.fn(),
    expenseAccount: { id: '2', display_name: 'Supplies' },
    setExpenseAccount: vi.fn(),
    vendor: null,
    setVendor: vi.fn(),
    customer: null,
    project: null,
    description: 'Taxi',
    setDescription: vi.fn(),
    isBillable: false,
    setIsBillable: vi.fn(),
    paymentAccountsSelect: emptySelect,
    expenseAccountsSelect: emptySelect,
    vendorsSelect: emptySelect,
    customersSelect: emptySelect,
    projectsSelect: emptySelect,
    onCustomerChange: vi.fn(),
    setProject: vi.fn(),
    handleSubmit: vi.fn(),
    submitting: false,
    canSubmit: true,
    ...overrides,
  }
}

describe('ExpenseForm', () => {
  beforeEach(() => {
    vi.mocked(useExpenseForm).mockReturnValue(mockFormState() as ReturnType<typeof useExpenseForm>)
  })

  it('renders title, fields, and submit action', () => {
    render(
      <LocaleProvider>
        <ExpenseForm />
      </LocaleProvider>,
    )

    expect(screen.getByText('Record an expense')).toBeInTheDocument()
    expect(screen.getByLabelText('Amount')).toHaveValue(12.34)
    expect(screen.getByLabelText('Date')).toHaveValue('2026-08-03')
    expect(screen.getByRole('button', { name: 'Submit expense' })).toBeEnabled()
  })

  it('disables submit when the form is incomplete', () => {
    vi.mocked(useExpenseForm).mockReturnValue(
      mockFormState({ canSubmit: false }) as ReturnType<typeof useExpenseForm>,
    )

    render(
      <LocaleProvider>
        <ExpenseForm />
      </LocaleProvider>,
    )

    expect(screen.getByRole('button', { name: 'Submit expense' })).toBeDisabled()
  })

  it('invokes the guarded submit handler', async () => {
    const user = userEvent.setup()
    const handleSubmit = vi.fn()
    vi.mocked(useExpenseForm).mockReturnValue(
      mockFormState({ handleSubmit }) as ReturnType<typeof useExpenseForm>,
    )

    render(
      <LocaleProvider>
        <ExpenseForm />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Submit expense' }))
    expect(handleSubmit).toHaveBeenCalled()
  })
})
