import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import { describe, expect, it, vi } from 'vitest'
import { ManageTimesheetUserClientsDialog } from './ManageTimesheetUserClientsDialog'
import type { User } from '../hooks/useTimesheetProvisioning'

const timesheetUser: User = {
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  qbo_employee_ref: '7',
  qbo_employee_name: 'Jane Doe',
  all_customers_access: false,
  assigned_customers: [{ id: '11', display_name: 'Acme Corp' }],
}

function buildDialogState(overrides: Partial<ReturnType<typeof import('../hooks/useManageTimesheetUserClients').useManageTimesheetUserClients>> = {}) {
  return {
    loading: false,
    saving: false,
    search: '',
    setSearch: vi.fn(),
    displayAllCustomersAccess: false,
    showClientAssignment: true,
    accessReady: true,
    filteredCustomers: [
      { id: '11', display_name: 'Acme Corp' },
      { id: '12', display_name: 'Beta LLC' },
    ],
    selectedRefs: ['11'],
    isCustomerSelected: (id: string | number) => String(id) === '11',
    toggleCustomer: vi.fn(),
    setAllCustomersAccess: vi.fn(),
    saveAssignments: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  }
}

describe('ManageTimesheetUserClientsDialog', () => {
  it('renders client checkboxes and forwards user actions', async () => {
    const user = userEvent.setup()
    const onClose = vi.fn()
    const setSearch = vi.fn()
    const toggleCustomer = vi.fn()
    const setAllCustomersAccess = vi.fn()
    const saveAssignments = vi.fn().mockResolvedValue(undefined)
    const dialog = buildDialogState({
      setSearch,
      toggleCustomer,
      setAllCustomersAccess,
      saveAssignments,
    })

    render(
      <LocaleProvider>
        <ManageTimesheetUserClientsDialog user={timesheetUser} onClose={onClose} dialog={dialog} />
      </LocaleProvider>,
    )

    expect(screen.getByRole('dialog', { name: /client access for jane doe/i })).toBeInTheDocument()
    expect(screen.getByText('Acme Corp')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/search clients/i), 'beta')
    expect(setSearch).toHaveBeenCalled()

    await user.click(screen.getByRole('checkbox', { name: /beta llc/i }))
    expect(toggleCustomer).toHaveBeenCalledWith('12')

    await user.click(screen.getByRole('checkbox', { name: /access to all clients/i }))
    expect(setAllCustomersAccess).toHaveBeenCalledWith(true)

    await user.click(screen.getByRole('button', { name: /^save$/i }))
    expect(saveAssignments).toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: /^cancel$/i }))
    expect(onClose).toHaveBeenCalled()
  })

  it('shows a loading message while client access is not ready', () => {
    const dialog = buildDialogState({ accessReady: false })

    render(
      <LocaleProvider>
        <ManageTimesheetUserClientsDialog user={timesheetUser} onClose={vi.fn()} dialog={dialog} />
      </LocaleProvider>,
    )

    expect(screen.getByText(/loading clients/i)).toBeInTheDocument()
  })
})
