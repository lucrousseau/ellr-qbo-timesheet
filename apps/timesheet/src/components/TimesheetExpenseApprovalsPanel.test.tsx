/**
 * @file Tests for the timesheet expense approvals panel.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider, useExpenseApprovals } from '@ellr/ui'
import { TimesheetExpenseApprovalsPanel } from './TimesheetExpenseApprovalsPanel'

vi.mock('@ellr/ui', async () => {
  const actual = await vi.importActual<typeof import('@ellr/ui')>('@ellr/ui')

  return {
    ...actual,
    useExpenseApprovals: vi.fn(),
  }
})

/**
 * Builds mocked expense approval hook state.
 * @param overrides Partial hook state overrides.
 * @returns Mock useExpenseApprovals return value.
 */
function mockApprovals(overrides: Record<string, unknown> = {}) {
  return {
    expenses: [],
    loading: false,
    loadingMore: false,
    hasMore: false,
    error: null,
    reviewingId: null,
    approveEntry: vi.fn(),
    rejectEntry: vi.fn(),
    loadMore: vi.fn(),
    ...overrides,
  }
}

describe('TimesheetExpenseApprovalsPanel', () => {
  beforeEach(() => {
    vi.mocked(useExpenseApprovals).mockReturnValue(
      mockApprovals() as ReturnType<typeof useExpenseApprovals>,
    )
  })

  it('shows an empty state when there are no pending expenses', () => {
    render(
      <LocaleProvider>
        <TimesheetExpenseApprovalsPanel
          enabled
          useAdminRoutes={false}
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText(/no pending expenses/i)).toBeInTheDocument()
  })

  it('renders pending expenses and load-more', async () => {
    const user = userEvent.setup()
    const loadMore = vi.fn()
    vi.mocked(useExpenseApprovals).mockReturnValue(
      mockApprovals({
        expenses: [
          {
            id: 3,
            user_id: 2,
            employee_name: 'Alex',
            amount: '12.00',
            txn_date: '2026-08-03',
            payment_type: 'Cash',
            payment_account_ref: '35',
            expense_account_ref: '7',
            is_billable: false,
            status: 'pending',
          },
        ],
        hasMore: true,
        loadMore,
      }) as ReturnType<typeof useExpenseApprovals>,
    )

    render(
      <LocaleProvider>
        <TimesheetExpenseApprovalsPanel
          enabled
          useAdminRoutes
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText(/Alex/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /load more/i }))
    expect(loadMore).toHaveBeenCalled()
  })

  it('shows loading and error states', () => {
    vi.mocked(useExpenseApprovals).mockReturnValue(
      mockApprovals({ loading: true, error: 'Failed to load' }) as ReturnType<
        typeof useExpenseApprovals
      >,
    )

    const { rerender } = render(
      <LocaleProvider>
        <TimesheetExpenseApprovalsPanel
          enabled
          useAdminRoutes={false}
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Failed to load')).toBeInTheDocument()

    vi.mocked(useExpenseApprovals).mockReturnValue(
      mockApprovals({ loading: true }) as ReturnType<typeof useExpenseApprovals>,
    )

    rerender(
      <LocaleProvider>
        <TimesheetExpenseApprovalsPanel
          enabled
          useAdminRoutes={false}
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )
  })
})
