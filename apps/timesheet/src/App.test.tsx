import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { authenticatedUser, buildApiClientMock, expectMessageClasses, fillLoginForm } from '@ellr/test-utils'
import { ApiError, createTimeActivity, fetchAppConfig, fetchCurrentUser, login, requestPasswordReset, resendVerificationEmail, resetPassword } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    createTimeActivity: vi.fn(),
    fetchAppConfig: vi.fn().mockResolvedValue({ require_email_verification: false }),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
    resendVerificationEmail: vi.fn(),
  }),
)

describe('Timesheet App', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/')
    vi.mocked(createTimeActivity).mockReset()
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(fetchAppConfig).mockReset()
    vi.mocked(fetchAppConfig).mockResolvedValue({ require_email_verification: false })
    vi.mocked(requestPasswordReset).mockReset()
    vi.mocked(resetPassword).mockReset()
    vi.mocked(resendVerificationEmail).mockReset()
  })

  it('shows an api outage message when session bootstrap fails', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(() => {
      const message = screen.getByText("Unable to reach the Laravel API.")
      expectMessageClasses(message, 'error')
    })
  })

  it('shows loading state while session is bootstrapping', async () => {
    vi.mocked(fetchCurrentUser).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    const loading = screen.getByText('Loading...')
    expect(loading).toHaveClass('text-slate-600')
    expect(screen.queryByRole('button', { name: /sign in/i })).not.toBeInTheDocument()
  })

  it('shows registration disabled on login', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new ApiError(403, 'API error: 403', 'registration_disabled'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByText(/registration disabled/i)).toBeInTheDocument()
    })
  })

  it('shows a login error when credentials are invalid', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new ApiError(401, 'API error: 401'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user, { password: 'wrong-password' })

    await waitFor(() => {
      expect(screen.getByText(/session expired/i)).toBeInTheDocument()
      expectMessageClasses(screen.getByText(/session expired/i), 'error')
    })
  })

  it('submits optional description fields', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.type(screen.getByLabelText(/description/i), 'Support client')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(createTimeActivity).toHaveBeenCalledWith(
        expect.objectContaining({
          description: 'Support client',
          start_time: '2026-07-27T09:00',
          end_time: '2026-07-27T17:00',
        }),
      )
    })
  })

  it('shows a generic login error for unexpected failures', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new Error('offline'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByText(/sign-in failed/i)).toBeInTheDocument()
    })
  })

  it('shows a service unavailable error on submission', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(503, 'API error: 503'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks is busy/i)).toBeInTheDocument()
    })
  })

  it('renders login form when user is not authenticated', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /timesheet/i })).toBeInTheDocument()
      expect(screen.getByText('Sign in to record your time')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
    })
  })

  it('renders the time entry form when authenticated with a configured employee', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /timesheet/i })).toBeInTheDocument()
      expect(screen.getByText('Signed in as test@example.com')).toBeInTheDocument()
      expect(screen.getByText(/Jane Doe \(7\)/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
      expect(document.querySelector('.bg-red-50')).not.toBeInTheDocument()
    })
  })

  it('shows the employee ref when only the ref is configured', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      qbo_employee_name: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/QBO employee:/i)).toBeInTheDocument()
      expect(screen.getByText('7')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })
  })

  it('shows a message when the qbo employee is not configured', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/quickbooks employee not configured/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /save/i })).not.toBeInTheDocument()
    })
  })

  it('submits a time entry through the api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(createTimeActivity).toHaveBeenCalledWith({
        start_time: '2026-07-27T09:00',
        end_time: '2026-07-27T17:00',
      })
      const success = screen.getByText('Time saved to QuickBooks Online.')
      expectMessageClasses(success, 'success')
    })
  })

  it('shows a quickbooks connection error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_not_connected'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks is not connected/i)).toBeInTheDocument()
    })
  })

  it('shows a quickbooks expired error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_expired'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection expired/i)).toBeInTheDocument()
    })
  })

  it('shows a generic error when submission fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new Error('failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(screen.getByText(/error while saving/i)).toBeInTheDocument()
      expectMessageClasses(screen.getByText(/error while saving/i), 'error')
    })
  })

  it('logs in from the timesheet app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('test@example.com', 'password')
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })
  })

  it('updates optional form fields', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/description/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/description/i), 'Support client')

    expect(screen.getByLabelText(/description/i)).toHaveValue('Support client')
  })

  it('logs out from the timesheet app', async () => {
    const user = userEvent.setup()
    const { logout } = await import('@ellr/api-client')
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

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

  it('shows a logout error when the api fails', async () => {
    const user = userEvent.setup()
    const { logout } = await import('@ellr/api-client')
    vi.mocked(logout).mockRejectedValue(new ApiError(500, 'API error: 500'))
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign out/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /sign out/i }))

    await waitFor(() => {
      expect(screen.getByText(/sign-out failed/i)).toBeInTheDocument()
    })
  })

  it('disables submit while saving', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockImplementation(
      () => new Promise((resolve) => setTimeout(() => resolve({ Id: '1' }), 100)),
    )

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    const savingButton = screen.getByRole('button', { name: 'Saving...' })
    expect(savingButton).toBeDisabled()
    expect(savingButton).toHaveClass('disabled:opacity-50')

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled()
    })
  })

  it('omits optional fields from the submission payload', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/start/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/end/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(createTimeActivity).toHaveBeenCalledWith({
        start_time: '2026-07-27T09:00',
        end_time: '2026-07-27T17:00',
      })
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

  it('requests a password reset link', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(requestPasswordReset).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /forgot password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /forgot password/i }))
    await user.type(screen.getByLabelText(/email/i), 'user@example.com')
    await user.click(screen.getByRole('button', { name: /send reset link/i }))

    await waitFor(() => {
      expect(requestPasswordReset).toHaveBeenCalledWith('user@example.com')
      expect(screen.getByText(/reset link has been sent/i)).toBeInTheDocument()
    })
  })

  it('shows email verification banner for unverified users when verification is required', async () => {
    vi.mocked(fetchAppConfig).mockResolvedValue({ require_email_verification: true })
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      email_verified_at: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/verify your email address/i)).toBeInTheDocument()
    })
  })

  it('allows unverified users to record time when verification is not required', async () => {
    vi.mocked(fetchAppConfig).mockResolvedValue({ require_email_verification: false })
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      email_verified_at: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
    })
    expect(screen.queryByText(/verify your email address/i)).not.toBeInTheDocument()
  })

  it('shows a verification success notice from the callback query', async () => {
    window.history.replaceState({}, '', '/?email=verified')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/email verified/i)).toBeInTheDocument()
    })
  })

  it('renders the reset password screen from the email link', async () => {
    window.history.replaceState({}, '', '/reset-password?token=abc&email=user%40example.com')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()
      expect(screen.getByText(/resetting password for user@example.com/i)).toBeInTheDocument()
    })
  })

  it('shows an error when the reset link is incomplete', async () => {
    window.history.replaceState({}, '', '/reset-password')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/reset link is invalid or incomplete/i)).toBeInTheDocument()
    })
  })

  it('updates the password from the reset screen', async () => {
    const user = userEvent.setup()
    window.history.replaceState({}, '', '/reset-password?token=abc&email=user%40example.com')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(resetPassword).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^new password$/i), 'new-password')
    await user.type(screen.getByLabelText(/confirm password/i), 'new-password')
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(resetPassword).toHaveBeenCalledWith({
        token: 'abc',
        email: 'user@example.com',
        password: 'new-password',
        passwordConfirmation: 'new-password',
      })
      expect(screen.getByText(/password updated/i)).toBeInTheDocument()
    })
  })

  it('shows reset password errors from the api', async () => {
    const user = userEvent.setup()
    window.history.replaceState({}, '', '/reset-password?token=abc&email=user%40example.com')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(resetPassword).mockRejectedValue(new ApiError(422, 'This password token is invalid.'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/^new password$/i), 'new-password')
    await user.type(screen.getByLabelText(/confirm password/i), 'new-password')
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

    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
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
    await user.type(screen.getByLabelText(/email/i), 'user@example.com')
    await user.click(screen.getByRole('button', { name: /send reset link/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to send the reset link/i)).toBeInTheDocument()
    })
  })

  it('resends the verification email for unverified users', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchAppConfig).mockResolvedValue({ require_email_verification: true })
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      email_verified_at: null,
    })
    vi.mocked(resendVerificationEmail).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /resend verification email/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /resend verification email/i }))

    await waitFor(() => {
      expect(resendVerificationEmail).toHaveBeenCalled()
      expect(screen.getByText(/verification link sent/i)).toBeInTheDocument()
    })
  })

  it('shows verification resend errors from the api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchAppConfig).mockResolvedValue({ require_email_verification: true })
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      email_verified_at: null,
    })
    vi.mocked(resendVerificationEmail).mockRejectedValue(new Error('network'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /resend verification email/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /resend verification email/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to send the verification email/i)).toBeInTheDocument()
    })
  })
})
