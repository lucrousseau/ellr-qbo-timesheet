/**
 * @file Tests for the local expenses hook.
 */

import { act, waitFor } from '@testing-library/react'
import { createExpense, deleteExpense, listExpenses, type Expense } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useExpenses } from './useExpenses'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listExpenses: vi.fn(),
    createExpense: vi.fn(),
    deleteExpense: vi.fn(),
  }
})

const sampleExpense: Expense = {
  id: 9,
  user_id: 2,
  amount: '42.50',
  txn_date: '2026-08-03',
  payment_type: 'Cash',
  payment_account_ref: '1',
  expense_account_ref: '2',
  is_billable: false,
  status: 'pending',
}

/**
 * Renders the expenses hook with flash callbacks.
 * @returns Hook result and mocked callbacks.
 */
function renderExpensesHook() {
  const onSuccess = vi.fn()
  const onError = vi.fn()
  const view = renderHookWithLocale(() =>
    useExpenses({
      enabled: true,
      onSuccess,
      onError,
    }),
  )

  return { ...view, onSuccess, onError }
}

describe('useExpenses', () => {
  beforeEach(() => {
    vi.mocked(listExpenses).mockReset()
    vi.mocked(createExpense).mockReset()
    vi.mocked(deleteExpense).mockReset()
    vi.mocked(listExpenses).mockResolvedValue({
      data: [sampleExpense],
      meta: { count: 1, max_results: 20, start_position: 1, truncated: false },
    })
    vi.mocked(createExpense).mockResolvedValue(sampleExpense)
    vi.mocked(deleteExpense).mockResolvedValue(undefined)
  })

  it('loads expenses when enabled', async () => {
    const { result } = renderExpensesHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    expect(listExpenses).toHaveBeenCalledWith({ max_results: 20, start_position: 1 })
  })

  it('creates an expense and refreshes the list', async () => {
    const { result, onSuccess } = renderExpensesHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.createExpense({
        amount: '10.00',
        txn_date: '2026-08-03',
        payment_account_ref: '1',
        expense_account_ref: '2',
      })
    })

    expect(createExpense).toHaveBeenCalled()
    expect(onSuccess).toHaveBeenCalledWith('Expense submitted for approval.')
    expect(listExpenses).toHaveBeenCalledTimes(2)
  })

  it('deletes an expense and refreshes the list', async () => {
    const { result, onSuccess } = renderExpensesHook()

    await waitFor(() => {
      expect(result.current.expenses).toHaveLength(1)
    })

    await act(async () => {
      await result.current.deleteExpense(9)
    })

    expect(deleteExpense).toHaveBeenCalledWith(9)
    expect(onSuccess).toHaveBeenCalledWith('Expense deleted.')
  })

  it('reports load errors', async () => {
    vi.mocked(listExpenses).mockRejectedValue(new Error('network'))

    const { result } = renderExpensesHook()

    await waitFor(() => {
      expect(result.current.error).toBeTruthy()
    })
  })

  it('clears expenses when disabled', () => {
    const onSuccess = vi.fn()
    const onError = vi.fn()

    const { result } = renderHookWithLocale(() =>
      useExpenses({
        enabled: false,
        onSuccess,
        onError,
      }),
    )

    expect(result.current.expenses).toEqual([])
    expect(listExpenses).not.toHaveBeenCalled()
  })
})
