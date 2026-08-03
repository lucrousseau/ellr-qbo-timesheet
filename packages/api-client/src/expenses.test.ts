/**
 * @file Unit tests for local expense API helpers.
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { resetCsrfStateForTests } from './api'
import {
  approveExpense,
  createExpense,
  listExpenses,
} from './expenses'
import { fetchQboExpenseAccounts, fetchQboVendors } from './timesheet'

describe('expenses api', () => {
  function mockCsrfCookie(token = 'csrf-token-value') {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}`
  }

  afterEach(() => {
    vi.unstubAllGlobals()
    document.cookie = ''
    resetCsrfStateForTests()
  })

  it('creates an expense through the api', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({
          data: {
            id: 7,
            amount: '12.50',
            txn_date: '2026-08-03',
            payment_type: 'Cash',
            payment_account_ref: '1',
            expense_account_ref: '2',
            is_billable: false,
            status: 'pending',
            user_id: 3,
          },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(
      createExpense({
        amount: '12.50',
        txn_date: '2026-08-03',
        payment_account_ref: '1',
        expense_account_ref: '2',
      }),
    ).resolves.toMatchObject({ id: 7, status: 'pending' })

    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/expenses',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          amount: '12.50',
          txn_date: '2026-08-03',
          payment_account_ref: '1',
          expense_account_ref: '2',
        }),
      }),
    )
  })

  it('lists expenses with optional pagination query params', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: [{ id: 1, amount: '5.00', status: 'pending' }],
        meta: {
          count: 1,
          max_results: 10,
          start_position: 1,
          truncated: false,
        },
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(listExpenses({ max_results: 10, start_position: 1 })).resolves.toMatchObject({
      data: [{ id: 1 }],
      meta: { truncated: false },
    })

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/expenses?start_position=1&max_results=10',
      expect.objectContaining({ method: 'GET' }),
    )
  })

  it('approves an expense through the reviewer route', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: { id: 4, status: 'approved', qbo_id: '88' },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(approveExpense(4)).resolves.toMatchObject({ id: 4, status: 'approved' })

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/expense-approvals/4/approve',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('approves an expense through the admin route', async () => {
    mockCsrfCookie()
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: { id: 4, status: 'approved', qbo_id: null },
        }),
      })
    vi.stubGlobal('fetch', fetchMock)

    await expect(approveExpense(4, true)).resolves.toMatchObject({ id: 4 })

    expect(fetchMock).toHaveBeenLastCalledWith(
      'http://localhost:8000/api/admin/expense-approvals/4/approve',
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('fetches expense accounts from quickbooks pickers', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: [{ id: '20', display_name: 'Office Supplies' }],
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchQboExpenseAccounts()).resolves.toEqual([
      { id: '20', display_name: 'Office Supplies' },
    ])

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/quickbooks/expense-accounts',
      expect.objectContaining({ credentials: 'include' }),
    )
  })

  it('fetches vendors and supports refresh bypass', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        data: [{ id: '9', display_name: 'Staples' }],
      }),
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(fetchQboVendors()).resolves.toEqual([{ id: '9', display_name: 'Staples' }])
    await fetchQboVendors({ refresh: true })

    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      'http://localhost:8000/api/quickbooks/vendors',
      expect.objectContaining({ credentials: 'include' }),
    )
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      'http://localhost:8000/api/quickbooks/vendors?refresh=1',
      expect.objectContaining({ credentials: 'include' }),
    )
  })
})
