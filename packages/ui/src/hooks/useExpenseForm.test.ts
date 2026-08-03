/**
 * @file Tests for the expense form hook.
 */

import { act, waitFor } from '@testing-library/react'
import {
  createExpense,
  fetchQboCustomers,
  fetchQboExpenseAccounts,
  fetchQboPaymentAccounts,
  fetchQboProjects,
  fetchQboVendors,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { FormEvent } from 'react'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useExpenseForm } from './useExpenseForm'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    createExpense: vi.fn(),
    fetchQboPaymentAccounts: vi.fn(),
    fetchQboExpenseAccounts: vi.fn(),
    fetchQboVendors: vi.fn(),
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
  }
})

describe('useExpenseForm', () => {
  beforeEach(() => {
    vi.mocked(createExpense).mockReset()
    vi.mocked(fetchQboPaymentAccounts).mockReset()
    vi.mocked(fetchQboExpenseAccounts).mockReset()
    vi.mocked(fetchQboVendors).mockReset()
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(createExpense).mockResolvedValue({
      id: 1,
      user_id: 2,
      amount: '12.00',
      txn_date: '2026-08-03',
      payment_type: 'Cash',
      payment_account_ref: '35',
      expense_account_ref: '7',
      is_billable: false,
      status: 'pending',
    })
    vi.mocked(fetchQboPaymentAccounts).mockResolvedValue([
      { id: '35', display_name: 'Checking' },
    ])
    vi.mocked(fetchQboExpenseAccounts).mockResolvedValue([
      { id: '7', display_name: 'Office' },
    ])
    vi.mocked(fetchQboVendors).mockResolvedValue([{ id: '56', display_name: 'Vendor' }])
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '12', display_name: 'Job' }])
  })

  it('submits a create payload and reports success', async () => {
    const onSuccess = vi.fn()
    const onCreated = vi.fn()
    const { result } = renderHookWithLocale(() =>
      useExpenseForm({ onSuccess, onCreated }),
    )

    act(() => {
      result.current.setAmount('25.50')
      result.current.setTxnDate('2026-08-03')
      result.current.setPaymentAccount({ id: '35', display_name: 'Checking' })
      result.current.setExpenseAccount({ id: '7', display_name: 'Office' })
      result.current.setVendor({ id: '56', display_name: 'Vendor' })
      result.current.setDescription('AI credits')
      result.current.setIsBillable(true)
    })

    const event = {
      preventDefault: vi.fn(),
    } as unknown as FormEvent

    await act(async () => {
      await result.current.handleSubmit(event)
    })

    await waitFor(() => {
      expect(createExpense).toHaveBeenCalledWith(
        expect.objectContaining({
          amount: '25.50',
          txn_date: '2026-08-03',
          payment_account_ref: '35',
          expense_account_ref: '7',
          vendor_ref: '56',
          description: 'AI credits',
          is_billable: true,
        }),
      )
    })

    expect(onSuccess).toHaveBeenCalled()
    expect(onCreated).toHaveBeenCalled()
    expect(result.current.amount).toBe('')
  })

  it('delegates to onSubmit when provided and clears the form', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined)
    const onCreated = vi.fn()
    const { result } = renderHookWithLocale(() =>
      useExpenseForm({ onSubmit, onCreated }),
    )

    act(() => {
      result.current.setAmount('10')
      result.current.setTxnDate('2026-08-03')
      result.current.setPaymentAccount({ id: '35', display_name: 'Checking' })
      result.current.setExpenseAccount({ id: '7', display_name: 'Office' })
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(onSubmit).toHaveBeenCalled()
    expect(createExpense).not.toHaveBeenCalled()
    expect(onCreated).toHaveBeenCalled()
  })

  it('reports create failures through onError', async () => {
    vi.mocked(createExpense).mockRejectedValueOnce(new Error('boom'))
    const onError = vi.fn()
    const { result } = renderHookWithLocale(() => useExpenseForm({ onError }))

    act(() => {
      result.current.setAmount('10')
      result.current.setTxnDate('2026-08-03')
      result.current.setPaymentAccount({ id: '35', display_name: 'Checking' })
      result.current.setExpenseAccount({ id: '7', display_name: 'Office' })
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(onError).toHaveBeenCalled()
  })

  it('clears the project when the customer changes', () => {
    const { result } = renderHookWithLocale(() => useExpenseForm({}))

    act(() => {
      result.current.setProject({ id: '12', display_name: 'Job' })
      result.current.onCustomerChange({ id: '11', display_name: 'Acme' })
    })

    expect(result.current.customer?.id).toBe('11')
    expect(result.current.project).toBeNull()
  })

  it('does not submit when required accounts are missing', async () => {
    const { result } = renderHookWithLocale(() => useExpenseForm({}))

    act(() => {
      result.current.setAmount('10')
      result.current.setTxnDate('2026-08-03')
    })

    await act(async () => {
      await result.current.handleSubmit({
        preventDefault: vi.fn(),
      } as unknown as FormEvent)
    })

    expect(createExpense).not.toHaveBeenCalled()
    expect(result.current.canSubmit).toBe(false)
  })
})
