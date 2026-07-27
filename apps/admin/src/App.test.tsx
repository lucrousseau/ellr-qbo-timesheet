import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { fillLoginForm, buildApiClientMock } from '@ellr/test-utils'
import {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchCurrentUser,
  fetchQuickBooksStatus,
  login,
  logout,
  updateQboEmployee,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    fetchQuickBooksStatus: vi.fn(),
    connectQuickBooks: vi.fn(),
    disconnectQuickBooks: vi.fn(),
    updateQboEmployee: vi.fn(),
  }),
)

describe('Admin App', () => {
  const originalLocation = window.location

  beforeEach(() => {
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(login).mockReset()
    vi.mocked(logout).mockReset()
    vi.mocked(fetchQuickBooksStatus).mockReset()
    vi.mocked(connectQuickBooks).mockReset()
    vi.mocked(disconnectQuickBooks).mockReset()
    vi.mocked(updateQboEmployee).mockReset()
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

  it('renders login form when user is not authenticated', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /sign in$/i })).toBeInTheDocument()
    })
  })

  it('shows quickbooks connection status when authenticated', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: true,
      realm_id: 'realm-42',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/Connected \(realm realm-42\)/i)).toBeInTheDocument()
    })
  })

  it('shows disconnected status when quickbooks is not connected', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({
      connected: false,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('redirects to quickbooks when connect succeeds', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
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
      expect(screen.getByRole('button', { name: /connect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connect quickbooks/i }))

    await waitFor(() => {
      expect(redirectedTo).toBe('https://intuit.example/oauth')
    })
  })

  it('shows an error when quickbooks connect fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(connectQuickBooks).mockRejectedValue(new Error('offline'))

    render(<App />)

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
    vi.mocked(login).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('test@example.com', 'password')
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('shows quickbooks connected notice from callback redirect', async () => {
    const replaceState = mockLocation('?quickbooks=connected')
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connected successfully/i)).toBeInTheDocument()
      expect(replaceState).toHaveBeenCalled()
    })
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

  it('shows bootstrap error when current user loading fails', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to reach the laravel api/i)).toBeInTheDocument()
    })
  })

  it('shows application bootstrap error for unexpected failures', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new Error('bootstrap failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to load the application/i)).toBeInTheDocument()
    })
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
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
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
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockRejectedValue(new Error('disconnect failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to disconnect quickbooks/i)).toBeInTheDocument()
    })
  })

  it('shows redirecting label while quickbooks connect is in progress', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(connectQuickBooks).mockImplementation(() => new Promise(() => {}))

    render(<App />)

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
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

    await waitFor(() => {
      expect(disconnectQuickBooks).toHaveBeenCalled()
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
    })
  })

  it('hides disconnect button when quickbooks status is unavailable', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockRejectedValue(new Error('status unavailable'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to load the application/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /disconnect quickbooks/i })).not.toBeInTheDocument()
    })
  })

  it('loads quickbooks status after login', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-99' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(fetchQuickBooksStatus).toHaveBeenCalled()
      expect(screen.getByText(/Connected \(realm realm-99\)/i)).toBeInTheDocument()
    })
  })

  it('logs out from the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
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

  it('saves the qbo employee mapping', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(updateQboEmployee).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      qbo_employee_ref: '7',
      qbo_employee_name: 'Jane Doe',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/qbo employee id/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/qbo employee id/i), '7')
    await user.type(screen.getByLabelText(/employee name/i), 'Jane Doe')
    await user.click(screen.getByRole('button', { name: /save employee/i }))

    await waitFor(() => {
      expect(updateQboEmployee).toHaveBeenCalledWith('7', 'Jane Doe')
      expect(screen.getByText(/quickbooks employee saved/i)).toBeInTheDocument()
    })
  })

  it('shows an error when qbo employee save fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(updateQboEmployee).mockRejectedValue(new Error('save failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/qbo employee id/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/qbo employee id/i), '7')
    await user.click(screen.getByRole('button', { name: /save employee/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to save the quickbooks employee/i)).toBeInTheDocument()
    })
  })

  it('disconnects quickbooks from the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks disconnected/i)).toBeInTheDocument()
    })
  })
})
