import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { authenticatedUser, buildApiClientMock, expectMessageClasses, fillLoginForm } from '@ellr/test-utils'
import { ApiError, createTimeActivity, fetchCurrentUser, login } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('@ellr/api-client', async () =>
  buildApiClientMock({
    createTimeActivity: vi.fn(),
  }),
)

describe('Timesheet App', () => {
  beforeEach(() => {
    vi.mocked(createTimeActivity).mockReset()
    vi.mocked(fetchCurrentUser).mockReset()
  })

  it('shows an api outage message when session bootstrap fails', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(() => {
      const message = screen.getByText("Impossible de joindre l'API Laravel.")
      expectMessageClasses(message, 'error')
    })
  })

  it('shows loading state while session is bootstrapping', async () => {
    vi.mocked(fetchCurrentUser).mockImplementation(() => new Promise(() => {}))

    render(<App />)

    const loading = screen.getByText('Chargement...')
    expect(loading).toHaveClass('text-slate-600')
    expect(screen.queryByRole('button', { name: /se connecter/i })).not.toBeInTheDocument()
  })

  it('shows registration disabled on login', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new ApiError(403, 'API error: 403', 'registration_disabled'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByText(/inscription désactivée/i)).toBeInTheDocument()
    })
  })

  it('shows a login error when credentials are invalid', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new ApiError(401, 'API error: 401'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user, { password: 'wrong-password' })

    await waitFor(() => {
      expect(screen.getByText(/session expirée/i)).toBeInTheDocument()
      expectMessageClasses(screen.getByText(/session expirée/i), 'error')
    })
  })

  it('submits optional description fields', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.type(screen.getByLabelText(/description/i), 'Support client')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

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
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(screen.getByText(/connexion impossible/i)).toBeInTheDocument()
    })
  })

  it('shows a service unavailable error on submission', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(503, 'API error: 503'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks est occupé/i)).toBeInTheDocument()
    })
  })

  it('renders login form when user is not authenticated', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /feuille de temps/i })).toBeInTheDocument()
      expect(screen.getByText('Connectez-vous pour enregistrer votre temps')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /se connecter/i })).toBeInTheDocument()
    })
  })

  it('renders the time entry form when authenticated with a configured employee', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /feuille de temps/i })).toBeInTheDocument()
      expect(screen.getByText('Connecté en tant que test@example.com')).toBeInTheDocument()
      expect(screen.getByText(/Jane Doe \(7\)/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
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
      expect(screen.getByText(/employé quickbooks non configuré/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /enregistrer/i })).not.toBeInTheDocument()
    })
  })

  it('submits a time entry through the api', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(createTimeActivity).toHaveBeenCalledWith({
        start_time: '2026-07-27T09:00',
        end_time: '2026-07-27T17:00',
      })
      const success = screen.getByText('Temps enregistré dans QuickBooks Online.')
      expectMessageClasses(success, 'success')
    })
  })

  it('shows a quickbooks connection error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_not_connected'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks n'est pas connecté/i)).toBeInTheDocument()
    })
  })

  it('shows a quickbooks expired error on forbidden responses', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new ApiError(403, 'API error: 403', 'quickbooks_expired'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(screen.getByText(/connexion quickbooks expirée/i)).toBeInTheDocument()
    })
  })

  it('shows a generic error when submission fails', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockRejectedValue(new Error('failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(screen.getByText(/erreur lors de l'enregistrement/i)).toBeInTheDocument()
      expectMessageClasses(screen.getByText(/erreur lors de l'enregistrement/i), 'error')
    })
  })

  it('logs in from the timesheet app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('test@example.com', 'password')
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /déconnexion/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnexion/i }))

    await waitFor(() => {
      expect(logout).toHaveBeenCalled()
      expect(screen.getByRole('button', { name: /se connecter/i })).toBeInTheDocument()
    })
  })

  it('shows a logout error when the api fails', async () => {
    const user = userEvent.setup()
    const { logout } = await import('@ellr/api-client')
    vi.mocked(logout).mockRejectedValue(new ApiError(500, 'API error: 500'))
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /déconnexion/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnexion/i }))

    await waitFor(() => {
      expect(screen.getByText(/déconnexion impossible/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    const savingButton = screen.getByRole('button', { name: 'Enregistrement...' })
    expect(savingButton).toBeDisabled()
    expect(savingButton).toHaveClass('disabled:opacity-50')

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Enregistrer' })).toBeEnabled()
    })
  })

  it('omits optional fields from the submission payload', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(authenticatedUser)
    vi.mocked(createTimeActivity).mockResolvedValue({ Id: '1' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /enregistrer/i })).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/début/i), '2026-07-27T09:00')
    await user.type(screen.getByLabelText(/fin/i), '2026-07-27T17:00')
    await user.click(screen.getByRole('button', { name: /enregistrer/i }))

    await waitFor(() => {
      expect(createTimeActivity).toHaveBeenCalledWith({
        start_time: '2026-07-27T09:00',
        end_time: '2026-07-27T17:00',
      })
    })
  })
})
