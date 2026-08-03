/**
 * @file Tests for pending expense approval hook.
 */

import { act, waitFor } from '@testing-library/react'
import {
  approveExpense,
  listPendingExpenseApprovals,
  rejectExpense,
  type Expense,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useExpenseApprovals } from './useExpenseApprovals'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listPendingExpenseApprovals: vi.fn(),
    approveExpense: vi.fn(),
    rejectExpense: vi.fn(),
  }
})

const pendingExpense: Expense = {
  id: 12,
  user_id: 2,
  employee_name: 'Bob LeMoche',
  amount: '25.00',
  txn_date: '2026-08-01',
  payment_type: 'Cash',
  payment_account_ref: '1',
  expense_account_ref: '2',
  is_billable: false,
  status: 'pending',
}

/**
 * Renders the expense approvals hook with default callbacks.
 * @param admin Whether to use the admin approval route.
 * @returns Hook render result and mocked callbacks.
 */
function renderApprovalsHook(admin = true) {
  const onSuccess = vi.fn()
  const onError = vi.fn()

  const view = renderHookWithLocale(() =>
    useExpenseApprovals({
      enabled: true,
      admin,
      onSuccess,
      onError,
    }),
  )

  return { ...view, onSuccess, onError }
}

describe('useExpenseApprovals', () => {
  beforeEach(() => {
    vi.mocked(listPendingExpenseApprovals).mockReset()
    vi.mocked(approveExpense).mockReset()
    vi.mocked(rejectExpense).mockReset()
    vi.mocked(listPendingExpenseApprovals).mockResolvedValue({
      data: [pendingExpense],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    })
    vi.mocked(approveExpense).mockResolvedValue({ ...pendingExpense, status: 'approved' })
    vi.mocked(rejectExpense).mockResolvedValue({ ...pendingExpense, status: 'rejected' })
  })

  it('loads pending expenses when enabled', async () => {
    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    expect(listPendingExpenseApprovals).toHaveBeenCalledWith(
      { max_results: 10, start_position: 1 },
      true,
    )
  })

  it('shows a queued sync message when approval returns without a quickbooks id', async () => {
    vi.mocked(approveExpense).mockResolvedValue({
      ...pendingExpense,
      status: 'approved',
      qbo_id: null,
    })

    const { result, onSuccess } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry(12)
    })

    expect(onSuccess).toHaveBeenCalledWith('Expense approved. QuickBooks sync is in progress.')
  })

  it('approves an expense and refreshes the list', async () => {
    vi.mocked(approveExpense).mockResolvedValue({
      ...pendingExpense,
      status: 'approved',
      qbo_id: '99',
    })

    const { result, onSuccess } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry(12)
    })

    expect(approveExpense).toHaveBeenCalledWith(12, true)
    expect(onSuccess).toHaveBeenCalledWith('Expense approved and sent to QuickBooks.')
    expect(listPendingExpenseApprovals).toHaveBeenCalledTimes(2)
  })

  it('rejects an expense through the supervisor route', async () => {
    const { result, onSuccess } = renderApprovalsHook(false)

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.rejectEntry(12, 'Missing receipt')
    })

    expect(rejectExpense).toHaveBeenCalledWith(12, 'Missing receipt', false)
    expect(onSuccess).toHaveBeenCalled()
  })

  it('reports api errors when loading fails', async () => {
    vi.mocked(listPendingExpenseApprovals).mockRejectedValue(new Error('network'))

    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.error).toBeTruthy()
    })
  })

  it('loads more expenses when additional pages are available', async () => {
    vi.mocked(listPendingExpenseApprovals)
      .mockResolvedValueOnce({
        data: [pendingExpense],
        meta: { count: 1, max_results: 10, start_position: 1, truncated: true },
      })
      .mockResolvedValueOnce({
        data: [{ ...pendingExpense, id: 13 }],
        meta: { count: 1, max_results: 10, start_position: 2, truncated: false },
      })

    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.hasMore).toBe(true)
    })

    act(() => {
      result.current.loadMore()
    })

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(2)
    })
  })

  it('surfaces approval errors through onError', async () => {
    vi.mocked(approveExpense).mockRejectedValue(new Error('network'))

    const { result, onError } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry(12)
    })

    expect(onError).toHaveBeenCalled()
  })
})
