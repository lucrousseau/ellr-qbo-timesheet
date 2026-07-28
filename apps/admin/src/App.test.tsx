import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildApiClientMock, fillLoginForm } from '@ellr/test-utils'
import { VALID_TEST_PASSWORD, VALID_TEST_PASSWORD_ALT } from '@ellr/test-utils'
import { ApiError, connectQuickBooks, disconnectQuickBooks, fetchCurrentUser, fetchQuickBooksStatus, login, logout, requestPasswordReset, resetPassword, updateQboEmployee, updateUserLocale } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    fetchQuickBooksStatus: vi.fn(),
    connectQuickBooks: vi.fn(),
    disconnectQuickBooks: vi.fn(),
    updateQboEmployee: vi.fn(),
    updateUserLocale: vi.fn(),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
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
    vi.mocked(updateUserLocale).mockReset()
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
      expect(login).toHaveBeenCalledWith('test@example.com', VALID_TEST_PASSWORD)
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
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })
    vi.mocked(fetchQuickBooksStatus)
      .mockResolvedValueOnce({ connected: true, realm_id: 'realm-42' })
      .mockResolvedValueOnce({ connected: false })

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

  it('shows quickbooks status error when status loading fails', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
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
    vi.mocked(login).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection denied or expired/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByText(/Not connected/i)).toBeInTheDocument()
      expect(screen.queryByText(/quickbooks connection denied or expired/i)).not.toBeInTheDocument()
    })
  })

  it('shows disconnecting label while quickbooks disconnect is in progress', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: true, realm_id: 'realm-42' })
    vi.mocked(disconnectQuickBooks).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnecting/i })).toBeDisabled()
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

  it('clears flash messages when logging out', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      qbo_employee_ref: '42',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })
    vi.mocked(updateQboEmployee).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      qbo_employee_ref: '42',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save employee/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /save employee/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks employee saved/i)).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
      expect(screen.queryByText(/quickbooks employee saved/i)).not.toBeInTheDocument()
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
    vi.mocked(disconnectQuickBooks).mockResolvedValue({ connected: false })
    vi.mocked(fetchQuickBooksStatus)
      .mockResolvedValueOnce({ connected: true, realm_id: 'realm-42' })
      .mockResolvedValueOnce({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /disconnect quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /disconnect quickbooks/i }))

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
    vi.mocked(updateUserLocale).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      locale: 'fr',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /preferences/i })).toBeInTheDocument()
    })

    await user.selectOptions(screen.getByLabelText(/language/i), 'fr')
    await user.click(screen.getByRole('button', { name: /^save$/i }))

    await waitFor(() => {
      expect(updateUserLocale).toHaveBeenCalledWith('fr')
      expect(screen.getByText(/preferences saved/i)).toBeInTheDocument()
    })
  })

  it('renders french admin copy when the user locale is french', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      locale: 'fr',
    })
    vi.mocked(fetchQuickBooksStatus).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /préférences/i })).toBeInTheDocument()
      expect(screen.getByRole('heading', { name: /connexion quickbooks online/i })).toBeInTheDocument()
    })
  })
})
