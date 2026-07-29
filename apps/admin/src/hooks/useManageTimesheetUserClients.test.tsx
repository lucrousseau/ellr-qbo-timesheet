import { act, renderHook, waitFor } from '@testing-library/react'
import {
  fetchAdminQboCustomers,
  fetchTimesheetUserCustomers,
  syncTimesheetUserCustomers,
} from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useManageTimesheetUserClients } from './useManageTimesheetUserClients'
import type { User } from './useTimesheetProvisioning'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchAdminQboCustomers: vi.fn(),
    fetchTimesheetUserCustomers: vi.fn(),
    syncTimesheetUserCustomers: vi.fn(),
  }
})

const timesheetUser: User = {
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  qbo_employee_ref: '7',
  qbo_employee_name: 'Jane Doe',
  all_customers_access: false,
  assigned_customers: [{ id: '11', display_name: 'Acme Corp' }],
}

function renderManageClientsHook(user: User | null = timesheetUser) {
  const onClose = vi.fn()
  const onSaved = vi.fn()
  const onError = vi.fn()
  const onSuccess = vi.fn()

  const view = renderHook(
    () =>
      useManageTimesheetUserClients({
        user,
        onClose,
        onSaved,
        onError,
        onSuccess,
      }),
    {
      wrapper: ({ children }) => <LocaleProvider>{children}</LocaleProvider>,
    },
  )

  return { ...view, onClose, onSaved, onError, onSuccess }
}

describe('useManageTimesheetUserClients', () => {
  beforeEach(() => {
    vi.mocked(fetchAdminQboCustomers).mockResolvedValue([
      { id: '11', display_name: 'Acme Corp' },
      { id: '12', display_name: 'Beta LLC' },
    ])
    vi.mocked(fetchTimesheetUserCustomers).mockResolvedValue({
      all_customers_access: false,
      data: [{ id: '11', display_name: 'Acme Corp' }],
    })
    vi.mocked(syncTimesheetUserCustomers).mockResolvedValue({
      all_customers_access: true,
      data: [],
    })
  })

  it('loads available and assigned customers when a user is selected', async () => {
    const { result } = renderManageClientsHook()

    await waitFor(() => {
      expect(result.current.accessReady).toBe(true)
    })

    expect(fetchAdminQboCustomers).toHaveBeenCalledWith({ refresh: true })
    expect(fetchTimesheetUserCustomers).toHaveBeenCalledWith(2)
    expect(result.current.filteredCustomers).toHaveLength(2)
    expect(result.current.isCustomerSelected('11')).toBe(true)
  })

  it('saves updated client access for the selected user', async () => {
    const { result, onClose, onSaved, onSuccess } = renderManageClientsHook()

    await waitFor(() => {
      expect(result.current.accessReady).toBe(true)
    })

    act(() => {
      result.current.setAllCustomersAccess(true)
    })

    await waitFor(() => {
      expect(result.current.displayAllCustomersAccess).toBe(true)
    })

    await act(async () => {
      await result.current.saveAssignments()
    })

    await waitFor(() => {
      expect(syncTimesheetUserCustomers).toHaveBeenCalledWith(2, {
        all_customers_access: true,
        customer_refs: [],
      })
      expect(onSaved).toHaveBeenCalled()
      expect(onSuccess).toHaveBeenCalled()
      expect(onClose).toHaveBeenCalled()
    })
  })

  it('filters customers by search query', async () => {
    const { result } = renderManageClientsHook()

    await waitFor(() => {
      expect(result.current.accessReady).toBe(true)
    })

    act(() => {
      result.current.setSearch('beta')
    })

    await waitFor(() => {
      expect(result.current.filteredCustomers).toEqual([
        { id: '12', display_name: 'Beta LLC' },
      ])
    })
  })

  it('toggles individual client selection', async () => {
    const { result } = renderManageClientsHook()

    await waitFor(() => {
      expect(result.current.accessReady).toBe(true)
    })

    act(() => {
      result.current.toggleCustomer('12')
    })

    await waitFor(() => {
      expect(result.current.isCustomerSelected('12')).toBe(true)
    })

    act(() => {
      result.current.toggleCustomer('11')
    })

    await waitFor(() => {
      expect(result.current.isCustomerSelected('11')).toBe(false)
    })
  })

  it('reports save failures to onError', async () => {
    vi.mocked(syncTimesheetUserCustomers).mockRejectedValue(new Error('save failed'))
    const { result, onError } = renderManageClientsHook()

    await waitFor(() => {
      expect(result.current.accessReady).toBe(true)
    })

    await act(async () => {
      await result.current.saveAssignments()
    })

    await waitFor(() => {
      expect(onError).toHaveBeenCalled()
    })
  })

  it('closes the dialog when loading customer access fails', async () => {
    vi.mocked(fetchTimesheetUserCustomers).mockRejectedValue(new Error('load failed'))

    const { onClose, onError } = renderManageClientsHook()

    await waitFor(() => {
      expect(onClose).toHaveBeenCalled()
      expect(onError).toHaveBeenCalled()
    })
  })
})
