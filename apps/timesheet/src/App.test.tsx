import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { authenticatedUser, buildApiClientMock, expectMessageClasses, fillLoginForm } from '@ellr/test-utils'
import { VALID_TEST_PASSWORD, VALID_TEST_PASSWORD_ALT } from '@ellr/test-utils'
import { ApiError, discardTimeTracker, fetchAppConfig, fetchCurrentUser, fetchQboCustomers, fetchQboProjects, fetchQboServices, fetchTimeTracker, logTimeTracker, login, logout, requestPasswordReset, resendVerificationEmail, resetPassword, updateTimeTracker, updateUserPreferences } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

const { mockActiveSession, defaultCustomers } = vi.hoisted(() => ({
  mockActiveSession: {
    customer_ref: null,
    customer_name: null,
    project_ref: null,
    project_name: null,
    service_ref: null,
    service_name: null,
    description: null,
    is_billable: false,
    accumulated_seconds: 3600,
    running_since: null,
    elapsed_seconds: 3600,
    is_running: false,
  },
  defaultCustomers: [{ id: '11', display_name: 'Acme Corp' }],
}))

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    discardTimeTracker: vi.fn().mockResolvedValue(undefined),
    fetchQboCustomers: vi.fn().mockResolvedValue(defaultCustomers),
    fetchQboProjects: vi.fn().mockResolvedValue([]),
    fetchQboServices: vi.fn().mockResolvedValue([]),
    fetchTimeTracker: vi.fn().mockResolvedValue(mockActiveSession),
    logTimeTracker: vi.fn(),
    updateTimeTracker: vi.fn().mockImplementation(async (payload) => ({
      ...mockActiveSession,
      ...payload,
      accumulated_seconds:
        payload.accumulated_seconds ?? mockActiveSession.accumulated_seconds,
      running_since: payload.is_running ? new Date().toISOString() : null,
      elapsed_seconds:
        payload.accumulated_seconds ?? mockActiveSession.elapsed_seconds,
      is_running: payload.is_running,
      is_billable: payload.is_billable ?? mockActiveSession.is_billable,
    })),
    fetchAppConfig: vi.fn().mockResolvedValue({
      require_email_verification: false,
      time_tracker_max_accumulated_seconds: 86_400,
    }),
    listTimeEntries: vi.fn().mockResolvedValue({
      data: [],
      meta: { count: 0, max_results: 10, start_position: 1, truncated: false },
    }),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
    resendVerificationEmail: vi.fn(),
    updateUserPreferences: vi.fn(),
  }),
)

describe('Timesheet App', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/')
    sessionStorage.clear()
    vi.mocked(logTimeTracker).mockReset()
    vi.mocked(fetchTimeTracker).mockReset()
    vi.mocked(fetchTimeTracker).mockResolvedValue(mockActiveSession)
    vi.mocked(updateTimeTracker).mockReset()
    vi.mocked(updateTimeTracker).mockImplementation(async (payload) => ({
      ...mockActiveSession,
      ...payload,
      accumulated_seconds:
        payload.accumulated_seconds ?? mockActiveSession.accumulated_seconds,
      running_since: payload.is_running ? new Date().toISOString() : null,
      elapsed_seconds:
        payload.accumulated_seconds ?? mockActiveSession.elapsed_seconds,
      is_running: payload.is_running,
      is_billable: payload.is_billable ?? mockActiveSession.is_billable,
    }))
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(fetchAppConfig).mockReset()
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: false,
      time_tracker_max_accumulated_seconds: 86_400,
    })
    vi.mocked(requestPasswordReset).mockReset()
    vi.mocked(resetPassword).mockReset()
    vi.mocked(resendVerificationEmail).mockReset()
    vi.mocked(logout).mockReset()
    vi.mocked(logout).mockResolvedValue(undefined)
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue(defaultCustomers)
    vi.mocked(fetchQboServices).mockReset()
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '1', display_name: 'Hours' }])
  })

  it('shows reconnect UI when session bootstrap fails transiently', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(
      () => {
        expect(screen.getByText(/reconnecting to the server/i)).toBeInTheDocument()
      },
      { timeout: 5000 },
    )
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

  it('syncs description changes to the timer session', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/description/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/description/i), 'Support client')
    await user.tab()

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalled()
    })
  })

  it('syncs billable checkbox changes to the timer session', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('checkbox', { name: /billable/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('checkbox', { name: /billable/i }))

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(expect.objectContaining({ is_billable: true }))
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
    vi.mocked(logTimeTracker).mockRejectedValue(new ApiError(503, 'API error: 503'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

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

  it('renders the timer panel when authenticated with a configured employee', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /timesheet/i })).toBeInTheDocument()
      expect(screen.getByText('Signed in as test@example.com')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
      expect(document.querySelector('.bg-red-50')).not.toBeInTheDocument()
    })
  })

  it('renders the timer panel when only the employee ref is configured', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      qbo_employee_name: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
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
      expect(screen.queryByRole('button', { name: /log time/i })).not.toBeInTheDocument()
    })
  })

  it('submits a time entry through the api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logTimeTracker).mockResolvedValue({
      id: 1,
      list_id: 'local:1',
      user_id: 1,
      start_time: '2026-07-27T09:00:00Z',
      end_time: '2026-07-27T17:00:00Z',
      duration_seconds: 28_800,
      is_billable: false,
      status: 'pending',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    await waitFor(() => {
      expect(logTimeTracker).toHaveBeenCalled()
      const success = screen.getByText('Time saved to QuickBooks Online.')
      expectMessageClasses(success, 'success')
    })
  })

  it('shows a quickbooks connection error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logTimeTracker).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_not_connected'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks is not connected/i)).toBeInTheDocument()
    })
  })

  it('shows a quickbooks expired error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logTimeTracker).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_expired'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks connection expired/i)).toBeInTheDocument()
    })
  })

  it('shows a generic error when submission fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logTimeTracker).mockRejectedValue(new Error('failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

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
      expect(login).toHaveBeenCalledWith('test@example.com', VALID_TEST_PASSWORD)
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
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
    vi.mocked(logTimeTracker).mockImplementation(
      () =>
        new Promise((resolve) =>
          setTimeout(
            () =>
              resolve({
                id: 1,
                list_id: 'local:1',
                user_id: 1,
                start_time: '2026-07-27T09:00:00Z',
                end_time: '2026-07-27T17:00:00Z',
                duration_seconds: 28_800,
                is_billable: false,
                status: 'pending',
              }),
            100,
          ),
        ),
    )

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    const savingButton = screen.getByRole('button', { name: 'Saving...' })
    expect(savingButton).toBeDisabled()
    expect(savingButton).toHaveClass('disabled:opacity-50')

    await waitFor(() => {
      expect(screen.getByText('Time saved to QuickBooks Online.')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /log time/i })).toBeDisabled()
    })
  })

  it('logs elapsed time through the tracker api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logTimeTracker).mockResolvedValue({
      id: 1,
      list_id: 'local:1',
      user_id: 1,
      start_time: '2026-07-27T09:00:00Z',
      end_time: '2026-07-27T17:00:00Z',
      duration_seconds: 28_800,
      is_billable: false,
      status: 'pending',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    await waitFor(() => {
      expect(logTimeTracker).toHaveBeenCalled()
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
      expect(requestPasswordReset).toHaveBeenCalledWith('user@example.com', { client: 'timesheet' })
      expect(screen.getByText(/reset link has been sent/i)).toBeInTheDocument()
    })
  })

  it('shows email verification banner for unverified users when verification is required', async () => {
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: true,
      time_tracker_max_accumulated_seconds: 86_400,
    })
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
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: false,
      time_tracker_max_accumulated_seconds: 86_400,
    })
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      email_verified_at: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
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

  it('logs out an active session before showing the reset password screen', async () => {
    window.history.replaceState({}, '', '/reset-password?token=abc&email=user%40example.com')
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(logout).mockResolvedValue(undefined)

    render(<App />)

    await waitFor(() => {
      expect(logout).toHaveBeenCalledTimes(1)
      expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()
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

    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/confirm password/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(resetPassword).toHaveBeenCalledWith({
        token: 'abc',
        email: 'user@example.com',
        password: VALID_TEST_PASSWORD_ALT,
        passwordConfirmation: VALID_TEST_PASSWORD_ALT,
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

    await user.type(screen.getByLabelText(/^new password$/i), VALID_TEST_PASSWORD_ALT)
    await user.type(screen.getByLabelText(/confirm password/i), VALID_TEST_PASSWORD_ALT)
    await user.click(screen.getByRole('button', { name: /update password/i }))

    await waitFor(() => {
      expect(screen.getByText(/password token is invalid/i)).toBeInTheDocument()
    })
  })

  it('shows the timesheet after sign-in when returning from a reset-password invite link', async () => {
    const user = userEvent.setup()
    window.history.replaceState({}, '', '/reset-password?token=abc&email=user%40example.com')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /back to sign in/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalled()
      expect(screen.getByRole('button', { name: /log time/i })).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /^sign in$/i })).not.toBeInTheDocument()
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
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: true,
      time_tracker_max_accumulated_seconds: 86_400,
    })
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
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: true,
      time_tracker_max_accumulated_seconds: 86_400,
    })
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

  it('renders french copy when the user locale is french', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      locale: 'fr',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /feuille de temps/i })).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /enregistrer le temps/i })).toBeInTheDocument()
    })
  })

  it('saves locale preferences from the timesheet app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      locale: 'en',
    })
    vi.mocked(updateUserPreferences).mockResolvedValue({
      ...authenticatedUser,
      locale: 'fr',
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: /preferences/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('tab', { name: /preferences/i }))

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

  it('does not load the timer session before authentication', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
    })

    expect(fetchTimeTracker).not.toHaveBeenCalled()
  })

  it('toggles the timer between play and pause', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /start timer/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /start timer/i }))

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(expect.objectContaining({ is_running: true }))
      expect(screen.getByRole('button', { name: /pause timer/i })).toBeInTheDocument()
    })
  })

  it('persists manual elapsed edits through start, pause, and log', async () => {
    const user = userEvent.setup()
    const editedSeconds = 12 * 3600 + 34 * 60
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchTimeTracker).mockResolvedValue({
      ...mockActiveSession,
      accumulated_seconds: 0,
      elapsed_seconds: 0,
      is_running: false,
      running_since: null,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /edit elapsed time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /edit elapsed time/i }))
    const input = screen.getByLabelText(/edit elapsed time/i)
    await waitFor(() => expect(input).toHaveFocus())
    await user.tripleClick(input)
    await user.keyboard('1234')
    await user.click(screen.getByRole('button', { name: /start timer/i }))

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(
        expect.objectContaining({
          accumulated_seconds: editedSeconds,
          is_running: true,
        }),
      )
    })

    await user.click(screen.getByRole('button', { name: /pause timer/i }))

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenLastCalledWith(
        expect.objectContaining({
          accumulated_seconds: editedSeconds,
          is_running: false,
        }),
      )
    })

    await user.click(screen.getByRole('button', { name: /log time/i }))

    await waitFor(() => {
      expect(logTimeTracker).toHaveBeenCalled()
    })
  })

  it('discards the active timer session', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /discard/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /discard/i }))

    await waitFor(() => {
      expect(discardTimeTracker).toHaveBeenCalled()
    })
  })

  it('clears a stale client after the picker loads allowed customers', async () => {
    const staleSession = {
      ...mockActiveSession,
      customer_ref: '99',
      customer_name: 'Stale Corp',
    }
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchTimeTracker).mockResolvedValue(staleSession)
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])

    render(<App />)

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
      expect(updateTimeTracker).toHaveBeenCalledWith(
        expect.objectContaining({
          customer_ref: null,
          customer_name: null,
        }),
      )
    })
  })

  it('loads customers when the client picker opens', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])

    render(<App />)

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
      expect(screen.getByLabelText(/^client$/i)).toBeInTheDocument()
    })

    await user.click(screen.getByLabelText(/^client$/i))

    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument()
    })
  })

  it('blocks time tracking when no clients are assigned to the user', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      all_customers_access: false,
    })
    vi.mocked(fetchQboCustomers).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/no clients are assigned to your account/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /log time/i })).not.toBeInTheDocument()
      expect(screen.queryByText(/recent time entries/i)).not.toBeInTheDocument()
    })
  })

  it('offers to discard a draft timer when tracking is blocked for missing clients', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      all_customers_access: false,
    })
    vi.mocked(fetchQboCustomers).mockResolvedValue([])
    vi.mocked(fetchTimeTracker).mockResolvedValue({
      ...mockActiveSession,
      accumulated_seconds: 900,
      elapsed_seconds: 900,
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/no clients are assigned to your account/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /discard draft time/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /discard draft time/i }))

    await waitFor(() => {
      expect(discardTimeTracker).toHaveBeenCalled()
    })
  })

  it('shows a no-clients warning when all-customers access is enabled but quickbooks has none', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...authenticatedUser,
      all_customers_access: true,
    })
    vi.mocked(fetchQboCustomers).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/no quickbooks clients are available/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /log time/i })).not.toBeInTheDocument()
    })
  })

  it('shows an error when quickbooks customer availability fails', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchQboCustomers).mockRejectedValue(new Error('offline'))
    vi.mocked(fetchQboServices).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to load quickbooks clients/i)).toBeInTheDocument()
    })

    expect(screen.queryByLabelText(/^client$/i)).not.toBeInTheDocument()
  })

  it('shows an error when the timer session fails to load', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchTimeTracker).mockRejectedValue(new Error('offline'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/unable to load timer state/i)).toBeInTheDocument()
    })
  })

  it('shows an error when discarding the timer fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(discardTimeTracker).mockRejectedValue(new Error('offline'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /discard/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /discard/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to discard the timer/i)).toBeInTheDocument()
    })
  })

  it('shows an error when timer sync fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(updateTimeTracker).mockRejectedValue(new Error('offline'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /start timer/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /start timer/i }))

    await waitFor(() => {
      expect(screen.getByText(/unable to save timer state/i)).toBeInTheDocument()
    })
  })

  it('selects a client and loads projects', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
    vi.mocked(fetchQboServices).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
      expect(screen.getByLabelText(/^client$/i)).toBeInTheDocument()
    })

    expect(screen.queryByLabelText(/^project$/i)).not.toBeInTheDocument()

    await user.click(screen.getByLabelText(/^client$/i))
    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument()
    })
    await user.click(screen.getByText('Acme Corp'))

    await waitFor(() => {
      expect(fetchQboProjects).toHaveBeenCalled()
      expect(screen.getByLabelText(/^project$/i)).toBeInTheDocument()
    })

    await user.click(screen.getByLabelText(/^project$/i))

    await waitFor(() => {
      expect(screen.getByText('Website')).toBeInTheDocument()
    })
  })

  it('hides the project picker when the selected client has no active projects', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([])

    render(<App />)

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
      expect(screen.getByLabelText(/^client$/i)).toBeInTheDocument()
    })

    await user.click(screen.getByLabelText(/^client$/i))
    await waitFor(() => {
      expect(screen.getByText('Acme Corp')).toBeInTheDocument()
    })
    await user.click(screen.getByText('Acme Corp'))

    await waitFor(() => {
      expect(fetchQboProjects).toHaveBeenCalled()
    })

    expect(screen.queryByLabelText(/^project$/i)).not.toBeInTheDocument()
  })

  it('loads services when the service picker opens', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Consulting' }])

    render(<App />)

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
      expect(screen.getByLabelText(/^service$/i)).toBeInTheDocument()
    })

    expect(fetchQboServices).not.toHaveBeenCalled()

    await user.click(screen.getByLabelText(/^service$/i))

    await waitFor(() => {
      expect(fetchQboServices).toHaveBeenCalled()
      expect(screen.getByText('Consulting')).toBeInTheDocument()
    })
  })
})
