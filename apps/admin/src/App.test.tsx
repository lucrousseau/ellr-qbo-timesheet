import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildApiClientMock, fillLoginForm } from '@ellr/test-utils'
import { VALID_TEST_PASSWORD, VALID_TEST_PASSWORD_ALT } from '@ellr/test-utils'
import { ApiError, changePassword, connectQuickBooks, createSuperAdminOrganization, createTimesheetUser, deleteSuperAdminOrganization, deleteTimesheetUser, disconnectQuickBooks, fetchAdminQboCustomers, fetchCurrentUser, fetchQboEmployees, fetchQuickBooksStatus, fetchSuperAdminOrganizations, fetchTimesheetUserCustomers, fetchTimesheetUsers, login, logout, requestPasswordReset, resetPassword, syncTimesheetUserCustomers, updateSuperAdminOrganization, updateUserPreferences } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { adminActiveTabStorageKey, LEGACY_ADMIN_TAB_ID } from './adminTabStorage'
import App from './App'

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    fetchQuickBooksStatus: vi.fn(),
    fetchQboEmployees: vi.fn(),
    fetchTimesheetUsers: vi.fn(),
    createTimesheetUser: vi.fn(),
    deleteTimesheetUser: vi.fn(),
    fetchAdminQboCustomers: vi.fn(),
    fetchTimesheetUserCustomers: vi.fn(),
    syncTimesheetUserCustomers: vi.fn(),
    fetchSuperAdminOrganizations: vi.fn(),
    createSuperAdminOrganization: vi.fn(),
    updateSuperAdminOrganization: vi.fn(),
    deleteSuperAdminOrganization: vi.fn(),
    connectQuickBooks: vi.fn(),
    disconnectQuickBooks: vi.fn(),
    updateUserPreferences: vi.fn(),
    changePassword: vi.fn(),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
  }),
)

describe('Admin App', () => {
  const originalLocation = window.location
  const adminUser = {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    is_admin: true,
  }
  const superAdminUser = {
    id: 3,
    name: 'Platform Operator',
    email: 'luc@ellr.ca',
    is_admin: false,
    is_super_admin: true,
  }
  const sampleClientOrganization = {
    id: 5,
    name: 'Gamma LLC',
    slug: 'gamma-llc',
    qbo_connected: false,
    users_count: 1,
    created_at: '2026-07-30T12:00:00Z',
  }

  async function openClientsTab(user: ReturnType<typeof userEvent.setup>) {
    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /client organizations/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: /client organizations/i }))
  }

  async function openIntegrationsTab(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('tab', { name: /int[eé]grations/i }))
  }

  async function openEmployeeDropdown(user: ReturnType<typeof userEvent.setup>) {
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /choose an employee/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /choose an employee/i }))

    await waitFor(() => {
      expect(fetchQboEmployees).toHaveBeenCalledWith(
        expect.objectContaining({ refresh: false }),
      )
    })
  }

  async function confirmQuickBooksDisconnect(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

    const dialog = screen.getByRole('alertdialog')
    await user.click(within(dialog).getByRole('button', { name: /^disconnect$/i }))
  }

  async function selectEmployeeOption(
    user: ReturnType<typeof userEvent.setup>,
    name: RegExp | string,
  ) {
    await waitFor(() => {
      expect(screen.getByRole('option', { name })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('option', { name }))
  }

  beforeEach(() => {
    sessionStorage.clear()
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(login).mockReset()
    vi.mocked(logout).mockReset()
    vi.mocked(fetchQuickBooksStatus).mockReset()
    vi.mocked(fetchQboEmployees).mockReset()
    vi.mocked(fetchTimesheetUsers).mockReset()
    vi.mocked(createTimesheetUser).mockReset()
    vi.mocked(deleteTimesheetUser).mockReset()
    vi.mocked(fetchAdminQboCustomers).mockReset()
    vi.mocked(fetchTimesheetUserCustomers).mockReset()
    vi.mocked(syncTimesheetUserCustomers).mockReset()
    vi.mocked(fetchSuperAdminOrganizations).mockReset()
    vi.mocked(createSuperAdminOrganization).mockReset()
    vi.mocked(updateSuperAdminOrganization).mockReset()
    vi.mocked(deleteSuperAdminOrganization).mockReset()
    vi.mocked(connectQuickBooks).mockReset()
    vi.mocked(disconnectQuickBooks).mockReset()
    vi.mocked(fetchQboEmployees).mockResolvedValue([])
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([])
    vi.mocked(updateUserPreferences).mockReset()
    vi.mocked(changePassword).mockReset()
    vi.mocked(requestPasswordReset).mockReset()
    vi.mocked(resetPassword).mockReset()
    vi.mocked(logout).mockResolvedValue(undefined)
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/',
        search: '',
        replaceState: vi.fn(),
        href: '',
      },
    })
  })

  function mockLocation(search: string) {
    const replaceState = vi.spyOn(window.history, 'replaceState').mockImplementation(() => {})
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/',
        search,
        href: '',
      },
    })

    return replaceState
  }

  it('shows loading state while session is bootstrapping', async () => {
    vi.mocked(fetchCurrentUser).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    const loading = screen.getByText('Loading...')
    expect(loading).toHaveClass('text-slate-600')
    expect(screen.queryByRole('button', { name: /sign in/i })).not.toBeInTheDocument()
  })

  it('renders login form when user is not authenticated', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /sign in$/i })).toBeInTheDocument()
    })
  })

  it('restores the active admin tab after reload', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: true,
      realm_id: 'realm-42',
    })

    const { unmount } = render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('integrations')

    unmount()
    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('integrations')
  })

  it('does not restore another user integrations tab', async () => {
    sessionStorage.setItem(adminActiveTabStorageKey(99), 'integrations')
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: true,
      realm_id: 'realm-42',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
    })

    expect(screen.queryByText(/Connected \(realm realm-42\)/i)).not.toBeInTheDocument()
    expect(sessionStorage.getItem(adminActiveTabStorageKey(99))).toBe('integrations')
    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).not.toBe('integrations')
  })

  it('falls back to preferences when stored tab value is invalid', async () => {
    sessionStorage.setItem(adminActiveTabStorageKey(adminUser.id), 'invalid')
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: true,
      realm_id: 'realm-42',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
    })

    expect(screen.queryByText(/Connected \(realm realm-42\)/i)).not.toBeInTheDocument()
    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('preferences')
  })

  it('shows quickbooks connection status when authenticated', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: true,
      realm_id: 'realm-42',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
    })
  })

  it('shows disconnected status when quickbooks is not connected', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: false,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('redirects to quickbooks when connect succeeds', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(connectQuickBooks).mockResolvedValue({ authorization_url: 'https://intuit.example/oauth' })

    let redirectedTo = ''
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...window.location,
        set href(value: string) {
          redirectedTo = value
        },
        get href() {
          return redirectedTo
        },
      },
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /redirecting/i })).toBeDisabled()
    })

    await waitFor(() => {
      expect(redirectedTo).toBe('https://intuit.example/oauth')
    })
  })

  it('shows an error when quickbooks connect fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(connectQuickBooks).mockRejectedValue(new Error('offline'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to start quickbooks connection/i)).toBeInTheDocument()
    })
  })

  it('logs in and loads quickbooks status', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('test@example.com', VALID_TEST_PASSWORD)
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('shows quickbooks connected notice from callback redirect', async () => {
    const replaceState = mockLocation('?quickbooks=connected')
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connected successfully/i)).toBeInTheDocument()
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
      expect(replaceState).toHaveBeenCalled()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('integrations')
  })

  it('switches to integrations after oauth error for admin users', async () => {
    mockLocation('?quickbooks=error&reason=oauth')
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection denied/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('integrations')
  })

  it('lets admins stay on preferences after oauth success focus is consumed', async () => {
    const user = userEvent.setup()
    mockLocation('?quickbooks=connected')
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: /preferences/i }))

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
      expect(screen.queryByText(/Connected \(realm realm-42\)/i)).not.toBeInTheDocument()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('preferences')
  })

  it('migrates legacy administrator tab storage on reload', async () => {
    sessionStorage.setItem(adminActiveTabStorageKey(adminUser.id), LEGACY_ADMIN_TAB_ID)
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    expect(sessionStorage.getItem(adminActiveTabStorageKey(adminUser.id))).toBe('integrations')
  })

  it('shows missing oauth params error from callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=missing_params')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/missing oauth parameters/i)).toBeInTheDocument()
    })
  })

  it('shows generic quickbooks error from callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=unknown')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to connect quickbooks/i)).toBeInTheDocument()
    })
  })

  it('shows oauth error notice from the callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=oauth')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection denied or expired/i)).toBeInTheDocument()
    })
  })

  it('shows reconnect UI when current user loading fails transiently', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(
      () => {
        expect(screen.getByText(/reconnecting to the server/i)).toBeInTheDocument()
      },
      { timeout: 5000 },
    )
  })

  it('shows reconnect UI for unexpected transient bootstrap failures', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(500, 'API error: 500'))

    render(<App />)

    await waitFor(
      () => {
        expect(screen.getByText(/reconnecting to the server/i)).toBeInTheDocument()
      },
      { timeout: 5000 },
    )
  })

  it('shows login error when credentials are rejected', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new Error('invalid credentials'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user, { password: 'wrong-password' })

    await waitFor(() => {
      expect(screen.getByText(/sign-in failed/i)).toBeInTheDocument()
    })
  })

  it('shows logout error when api fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(logout).mockRejectedValue(new Error('logout failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign out/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(screen.getByText(/sign-out failed/i)).toBeInTheDocument()
    })
  })

  it('shows disconnect error when quickbooks disconnect fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockRejectedValue(new Error('disconnect failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await confirmQuickBooksDisconnect(user)

    await waitFor(() => {
      expect(screen.getByText(/unable to disconnect quickbooks/i)).toBeInTheDocument()
    })
  })

  it('shows redirecting label while quickbooks connect is in progress', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(connectQuickBooks).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Redirecting/i })).toBeDisabled()
    })
  })

  it('calls quickbooks disconnect', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })
    vi.mocked(fetchQuickBooksStatus)
      .mockResolvedValueOnce({ connected: true, realm_id: 'realm-42' })
      .mockResolvedValueOnce({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await confirmQuickBooksDisconnect(user)

    await waitFor(() => {
      expect(disconnectQuickBooks).toHaveBeenCalled()
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('shows quickbooks status error when status loading fails', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockRejectedValue(new Error('status unavailable'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to load quickbooks status/i)).toBeInTheDocument()
      expect(screen.queryByText(/unable to load the application/i)).not.toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /disconnect quickbooks/i })).not.toBeInTheDocument()
    })
  })

  it('clears oauth flash messages after login', async () => {
    const user = userEvent.setup()
    mockLocation('?quickbooks=error&reason=oauth')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection denied or expired/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
      expect(screen.queryByText(/quickbooks connection denied or expired/i)).not.toBeInTheDocument()
    })
  })

  it('shows disconnecting label while quickbooks disconnect is in progress', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await confirmQuickBooksDisconnect(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnecting/i })).toBeDisabled()
    })
  })

  it('loads quickbooks status after login', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-99' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(fetchQuickBooksStatus).toHaveBeenCalled()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-99\)/i)).toBeInTheDocument()
    })
  })

  it('logs out from the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign out/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(logout).toHaveBeenCalled()
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
    })
  })

  it('clears flash messages when logging out', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([
      { id: '42', display_name: 'Jane Doe', email: 'jane@example.com' },
    ])
    vi.mocked(createTimesheetUser).mockResolvedValue({
      id: 2,
      name: 'Jane Doe',
      email: 'jane@example.com',
      qbo_employee_ref: '42',
      qbo_employee_name: 'Jane Doe',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /create timesheet access/i })).toBeInTheDocument()
    })

    await openEmployeeDropdown(user)
    await selectEmployeeOption(user, /jane doe/i)
    await user.click(screen.getByRole('button', { name: /create timesheet access/i }))

    await waitFor(() => {
      expect(screen.getByText(/timesheet access created/i)).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
      expect(screen.queryByText(/timesheet access created/i)).not.toBeInTheDocument()
    })
  })

  it('does not fetch employees until the integrations dropdown is opened', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([
      { id: '7', display_name: 'Jane Doe', email: 'jane@example.com' },
    ])
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    expect(fetchQboEmployees).not.toHaveBeenCalled()

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(fetchTimesheetUsers).toHaveBeenCalled()
    })

    expect(fetchQboEmployees).not.toHaveBeenCalled()

    await openEmployeeDropdown(user)

    expect(fetchQboEmployees).toHaveBeenCalledTimes(1)
    expect(screen.getByRole('button', { name: /choose an employee/i })).toBeEnabled()
  })

  it('does not surface an error when the employee dropdown closes during loading', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockImplementation(({ signal } = {}) => {
      return new Promise((_resolve, reject) => {
        signal?.addEventListener('abort', () => {
          reject(new DOMException('Aborted', 'AbortError'))
        })
      })
    })
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)
    await openEmployeeDropdown(user)

    expect(screen.getByText(/loading employees/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /choose an employee/i }))

    await waitFor(() => {
      expect(screen.queryByText(/loading employees/i)).not.toBeInTheDocument()
    })

    expect(screen.queryByText(/unable to load timesheet provisioning data/i)).not.toBeInTheDocument()
  })

  it('shows an error when employee loading fails on dropdown open', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockRejectedValue(new ApiError(503, 'API error: 503', 'quickbooks_busy'))
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)
    await openEmployeeDropdown(user)

    await waitFor(() => {
      expect(screen.queryByText(/employee list refreshed from quickbooks/i)).not.toBeInTheDocument()
      expect(screen.getByText(/quickbooks is busy/i)).toBeInTheDocument()
    })
  })

  it('creates timesheet access for a quickbooks employee', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([
      { id: '7', display_name: 'Jane Doe', email: 'jane@example.com' },
    ])
    vi.mocked(createTimesheetUser).mockResolvedValue({
      id: 2,
      name: 'Jane Doe',
      email: 'jane@example.com',
      qbo_employee_ref: '7',
      qbo_employee_name: 'Jane Doe',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /choose an employee/i })).toBeInTheDocument()
    })

    await openEmployeeDropdown(user)
    await selectEmployeeOption(user, /jane doe/i)

    expect(screen.getByText(/managed in quickbooks/i)).toBeInTheDocument()
    expect(screen.getByText('jane@example.com')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /create timesheet access/i }))

    await waitFor(() => {
      expect(createTimesheetUser).toHaveBeenCalledWith({
        qbo_employee_ref: '7',
      })
      expect(screen.getByText(/timesheet access created/i)).toBeInTheDocument()
    })
  })

  it('manages client access for a provisioned user', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([])
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([
      {
        id: 2,
        name: 'Jane Doe',
        email: 'jane@example.com',
        qbo_employee_ref: '7',
        qbo_employee_name: 'Jane Doe',
        all_customers_access: false,
        assigned_customers: [{ id: '11', display_name: 'Acme Corp' }],
      },
    ])
    vi.mocked(fetchAdminQboCustomers).mockResolvedValue([
      { id: '11', display_name: 'Acme Corp' },
      { id: '12', display_name: 'Beta LLC' },
    ])
    vi.mocked(fetchTimesheetUserCustomers).mockResolvedValue({
      all_customers_access: false,
      data: [{ id: '11', display_name: 'Acme Corp' }],
    })
    vi.mocked(syncTimesheetUserCustomers).mockResolvedValue({
      all_customers_access: false,
      data: [
        { id: '11', display_name: 'Acme Corp' },
        { id: '12', display_name: 'Beta LLC' },
      ],
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /manage clients/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /manage clients/i }))

    const dialog = await screen.findByRole('dialog', { name: /client access for jane doe/i })

    await waitFor(() => {
      expect(fetchAdminQboCustomers).toHaveBeenCalledWith({ refresh: true })
      expect(fetchTimesheetUserCustomers).toHaveBeenCalledWith(2)
      expect(within(dialog).getByRole('checkbox', { name: /access to all clients/i })).toBeEnabled()
      expect(within(dialog).getByText('Acme Corp')).toBeInTheDocument()
    })
  })

  it('removes timesheet access for a provisioned user', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([])
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([
      {
        id: 2,
        name: 'Jane Doe',
        email: 'jane@example.com',
        qbo_employee_ref: '7',
        qbo_employee_name: 'Jane Doe',
      },
    ])
    vi.mocked(deleteTimesheetUser).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /remove timesheet access for jane doe/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /remove timesheet access for jane doe/i }))

    const dialog = screen.getByRole('alertdialog')
    expect(within(dialog).getByText(/deletes the timesheet app account/i)).toBeInTheDocument()
    expect(within(dialog).getByText(/hours already logged in quickbooks online are not deleted/i)).toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: /^remove access$/i }))

    await waitFor(() => {
      expect(deleteTimesheetUser).toHaveBeenCalledWith(2)
      expect(screen.getByText(/timesheet access removed/i)).toBeInTheDocument()
      expect(screen.queryByText('Jane Doe')).not.toBeInTheDocument()
    })
  })

  it('keeps the remove dialog open when timesheet access removal fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([])
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([
      {
        id: 2,
        name: 'Jane Doe',
        email: 'jane@example.com',
        qbo_employee_ref: '7',
        qbo_employee_name: 'Jane Doe',
      },
    ])
    vi.mocked(deleteTimesheetUser).mockRejectedValue(new Error('delete failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /remove timesheet access for jane doe/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /remove timesheet access for jane doe/i }))

    const dialog = screen.getByRole('alertdialog')
    await user.click(within(dialog).getByRole('button', { name: /^remove access$/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to remove timesheet access/i)).toBeInTheDocument()
      expect(screen.getByRole('alertdialog')).toBeInTheDocument()
      expect(screen.getByText('Jane Doe')).toBeInTheDocument()
    })
  })

  it('shows an error when timesheet access creation fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(fetchQboEmployees).mockResolvedValue([
      { id: '7', display_name: 'Jane Doe', email: 'jane@example.com' },
    ])
    vi.mocked(createTimesheetUser).mockRejectedValue(new Error('create failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /choose an employee/i })).toBeInTheDocument()
    })

    await openEmployeeDropdown(user)
    await selectEmployeeOption(user, /jane doe/i)
    await user.click(screen.getByRole('button', { name: /create timesheet access/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to create timesheet access/i)).toBeInTheDocument()
    })
  })

  it('disconnects quickbooks from the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })
    vi.mocked(fetchQuickBooksStatus)
      .mockResolvedValueOnce({ connected: true, realm_id: 'realm-42' })
      .mockResolvedValueOnce({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await confirmQuickBooksDisconnect(user)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks disconnected/i)).toBeInTheDocument()
    })
  })

  it('opens the forgot password screen', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /forgot password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /forgot password/i }))

    expect(screen.getByRole('heading', { name: /forgot password/i })).toBeInTheDocument()
  })

  it('requests a password reset link for the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(requestPasswordReset).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /forgot password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /forgot password/i }))
    await user.type(screen.getByLabelText(/email/i), 'admin@example.com')
    await user.click(screen.getByRole('button', { name: /send reset link/i }))

    await waitFor(() => {
      expect(requestPasswordReset).toHaveBeenCalledWith('admin@example.com', { client: 'admin' })
      expect(screen.getByText(/reset link has been sent/i)).toBeInTheDocument()
    })
  })

  it('renders the reset password screen from the email link', async () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/reset-password',
        search: '?token=abc&email=admin%40example.com',
        href: '',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()
    })
  })

  it('logs out an active session before showing the reset password screen', async () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/reset-password',
        search: '?token=abc&email=admin%40example.com',
        href: '',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(logout).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(logout).toHaveBeenCalledTimes(1)
      expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()
    })
  })

  it('shows an error when the reset link is incomplete', async () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/reset-password',
        search: '',
        href: '',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/reset link is invalid or incomplete/i)).toBeInTheDocument()
    })
  })

  it('updates the password from the reset screen', async () => {
    const user = userEvent.setup()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/reset-password',
        search: '?token=abc&email=admin%40example.com',
        href: '',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(resetPassword).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/confirm password/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(resetPassword).toHaveBeenCalledWith({
        token: 'abc',
        email: 'admin@example.com',
        password: VALID_TEST_PASSWORD_ALT,
        passwordConfirmation: VALID_TEST_PASSWORD_ALT,
      })
      expect(screen.getByText(/password updated/i)).toBeInTheDocument()
    })
  })

  it('shows reset password errors from the api', async () => {
    const user = userEvent.setup()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: {
        ...originalLocation,
        pathname: '/reset-password',
        search: '?token=abc&email=admin%40example.com',
        href: '',
        replaceState: vi.fn(),
      },
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(resetPassword).mockRejectedValue(new ApiError(422, 'This password token is invalid.'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/confirm password/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(screen.getByText(/password token is invalid/i)).toBeInTheDocument()
    })
  })

  it('returns to sign in from forgot password', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /forgot password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /forgot password/i }))
    await user.click(screen.getByRole('button', { name: /back to sign in/i }))

    expect(screen.getByRole('heading', { name: /sign in$/i })).toBeInTheDocument()
  })

  it('shows forgot password errors from the api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(requestPasswordReset).mockRejectedValue(new Error('network'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /forgot password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /forgot password/i }))
    await user.type(screen.getByLabelText(/email/i), 'admin@example.com')
    await user.click(screen.getByRole('button', { name: /send reset link/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to send the reset link/i)).toBeInTheDocument()
    })
  })

  it('saves locale preferences', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      locale: 'en',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(updateUserPreferences).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      locale: 'fr',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /preferences/i })).toBeInTheDocument()
    })

    await user.click(screen.getByLabelText(/language/i))
    await user.click(screen.getByRole('option', { name: /french/i }))
    await user.click(screen.getByRole('button', { name: /^save$/i }))

    await waitFor(() => {
      expect(updateUserPreferences).toHaveBeenCalledWith({
        locale: 'fr',
        timezone: 'UTC',
      })
      expect(screen.getByText(/preferences saved/i)).toBeInTheDocument()
    })
  })

  it('renders french admin copy when the user locale is french', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...adminUser,
      locale: 'fr',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /préférences/i })).toBeInTheDocument()
    })

    await openIntegrationsTab(user)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /connexion quickbooks online/i })).toBeInTheDocument()
    })
  })

  it('shows preferences and integrations tabs for admin users', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /preferences/i })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: /integrations/i })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
    })
  })

  it('changes password from the preferences tab', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(changePassword).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^current password$/i), VALID_TEST_PASSWORD)
    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/^confirm password$/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(changePassword).toHaveBeenCalledWith(
        VALID_TEST_PASSWORD,
        VALID_TEST_PASSWORD_ALT,
        VALID_TEST_PASSWORD_ALT,
      )
      expect(screen.getByText(/password updated/i)).toBeInTheDocument()
    })
  })

  it('hides the integrations tab and skips quickbooks status for non-admin users', async () => {
    sessionStorage.setItem(adminActiveTabStorageKey(2), 'integrations')
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 2,
      name: 'Timesheet User',
      email: 'timesheet@example.com',
      is_admin: false,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /preferences/i })).toBeInTheDocument()
      expect(screen.queryByRole('tab', { name: /integrations/i })).not.toBeInTheDocument()
    })

    expect(fetchQuickBooksStatus).not.toHaveBeenCalled()
    expect(screen.queryByText(/administrator access required/i)).not.toBeInTheDocument()
    expect(sessionStorage.getItem(adminActiveTabStorageKey(2))).toBe('preferences')
  })

  it('shows the clients tab for super admins without quickbooks integrations', async () => {
    sessionStorage.setItem(adminActiveTabStorageKey(3), 'integrations')
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /client organizations/i })).toBeInTheDocument()
      expect(screen.queryByRole('tab', { name: /integrations/i })).not.toBeInTheDocument()
      expect(screen.queryByText(/quickbooks online connection/i)).not.toBeInTheDocument()
    })

    expect(fetchQuickBooksStatus).not.toHaveBeenCalled()
    expect(sessionStorage.getItem(adminActiveTabStorageKey(3))).toBe('preferences')
  })

  it('shows password policy hints and blocks weak passwords when creating client organizations', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)
    vi.mocked(fetchSuperAdminOrganizations).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /client organizations/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: /client organizations/i }))

    await waitFor(() => {
      expect(screen.getByText(/password requirements/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/organization name/i), 'Acme Corp')
    await user.type(screen.getByLabelText(/administrator name/i), 'Acme Admin')
    await user.type(screen.getByLabelText(/administrator email/i), 'admin@acme.test')
    await user.type(screen.getByLabelText(/^administrator password$/i), 'short')
    await user.type(screen.getByLabelText(/^confirm password$/i), 'short')
    await user.click(screen.getByRole('button', { name: /create organization/i }))

    await waitFor(() => {
      expect(screen.getByText(/the password must be at least 12 characters/i)).toBeInTheDocument()
    })

    expect(createSuperAdminOrganization).not.toHaveBeenCalled()
  })

  it('creates a client organization for platform super administrators', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)
    vi.mocked(fetchSuperAdminOrganizations).mockResolvedValue([])
    vi.mocked(createSuperAdminOrganization).mockResolvedValue(sampleClientOrganization)

    render(<App />)

    await openClientsTab(user)

    await user.type(screen.getByLabelText(/organization name/i), 'Gamma LLC')
    await user.type(screen.getByLabelText(/administrator name/i), 'Gamma Admin')
    await user.type(screen.getByLabelText(/administrator email/i), 'gamma@client.test')
    await user.type(screen.getByLabelText(/^administrator password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/^confirm password$/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /create organization/i }))

    await waitFor(() => {
      expect(createSuperAdminOrganization).toHaveBeenCalledWith({
        organization_name: 'Gamma LLC',
        name: 'Gamma Admin',
        email: 'gamma@client.test',
        password: VALID_TEST_PASSWORD_ALT,
        password_confirmation: VALID_TEST_PASSWORD_ALT,
      })
      expect(screen.getByText(/client organization created/i)).toBeInTheDocument()
      expect(screen.getByText('Gamma LLC')).toBeInTheDocument()
    })
  })

  it('renames a client organization for platform super administrators', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)
    vi.mocked(fetchSuperAdminOrganizations).mockResolvedValue([sampleClientOrganization])
    vi.mocked(updateSuperAdminOrganization).mockResolvedValue({
      ...sampleClientOrganization,
      name: 'Gamma Corp',
    })

    render(<App />)

    await openClientsTab(user)

    await user.click(screen.getByRole('button', { name: /^rename$/i }))

    const nameField = screen.getByDisplayValue('Gamma LLC')
    await user.clear(nameField)
    await user.type(nameField, 'Gamma Corp')
    await user.click(screen.getByRole('button', { name: /save name/i }))

    await waitFor(() => {
      expect(updateSuperAdminOrganization).toHaveBeenCalledWith(5, { name: 'Gamma Corp' })
      expect(screen.getByText(/organization name updated/i)).toBeInTheDocument()
      expect(screen.getByText('Gamma Corp')).toBeInTheDocument()
    })
  })

  it('deletes a client organization for platform super administrators', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)
    vi.mocked(fetchSuperAdminOrganizations).mockResolvedValue([sampleClientOrganization])
    vi.mocked(deleteSuperAdminOrganization).mockResolvedValue(undefined)

    render(<App />)

    await openClientsTab(user)

    await user.click(screen.getByRole('button', { name: /delete organization/i }))

    const dialog = screen.getByRole('alertdialog')
    await user.click(within(dialog).getByRole('button', { name: /delete organization/i }))

    await waitFor(() => {
      expect(deleteSuperAdminOrganization).toHaveBeenCalledWith(5)
      expect(screen.getByText(/client organization deleted/i)).toBeInTheDocument()
      expect(screen.queryByText('Gamma LLC')).not.toBeInTheDocument()
    })
  })

  it('shows a localized error when deleting the super administrators own organization', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(superAdminUser)
    vi.mocked(fetchSuperAdminOrganizations).mockResolvedValue([sampleClientOrganization])
    vi.mocked(deleteSuperAdminOrganization).mockRejectedValue(
      new ApiError(
        409,
        'You cannot delete the organization that owns your account.',
        'cannot_delete_own_organization',
      ),
    )

    render(<App />)

    await openClientsTab(user)

    await user.click(screen.getByRole('button', { name: /delete organization/i }))

    const dialog = screen.getByRole('alertdialog')
    await user.click(within(dialog).getByRole('button', { name: /delete organization/i }))

    await waitFor(() => {
      expect(screen.getByText(/you cannot delete the organization that owns your account/i)).toBeInTheDocument()
    })
  })

  it('shows a password change error when the api rejects the current password', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(adminUser)
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(changePassword).mockRejectedValue(
      new ApiError(422, 'The password is incorrect.', 'validation_error'),
    )

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^current password$/i), VALID_TEST_PASSWORD)
    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/^confirm password$/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(changePassword).toHaveBeenCalled()
      expect(screen.getByText(/password is incorrect/i)).toBeInTheDocument()
    })
  })
})
