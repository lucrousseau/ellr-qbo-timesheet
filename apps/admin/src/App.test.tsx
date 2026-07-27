import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { fillLoginForm } from '@ellr/test-utils'
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

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')
  return {
    ...actual,
    fetchCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
    fetchQuickBooksStatus: vi.fn(),
    connectQuickBooks: vi.fn(),
    disconnectQuickBooks: vi.fn(),
    updateQboEmployee: vi.fn(),
  }
})

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
      expect(screen.getByRole('heading', { name: /connexion$/i })).toBeInTheDocument()
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
      expect(screen.getByText(/connecté \(realm realm-42\)/i)).toBeInTheDocument()
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
      expect(screen.getByText(/non connecté/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /connecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connecter quickbooks/i }))

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
      expect(screen.getByRole('button', { name: /connecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connecter quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/impossible de démarrer la connexion quickbooks/i)).toBeInTheDocument()
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
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(login).toHaveBeenCalledWith('test@example.com', 'password')
      expect(screen.getByText(/non connecté/i)).toBeInTheDocument()
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
      expect(screen.getByText(/quickbooks connecté avec succès/i)).toBeInTheDocument()
      expect(replaceState).toHaveBeenCalled()
    })
  })

  it('shows missing oauth params error from callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=missing_params')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/paramètres oauth manquants/i)).toBeInTheDocument()
    })
  })

  it('shows generic quickbooks error from callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=unknown')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/connexion quickbooks impossible/i)).toBeInTheDocument()
    })
  })

  it('shows oauth error notice from the callback redirect', async () => {
    mockLocation('?quickbooks=error&reason=oauth')
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/connexion quickbooks refusée ou expirée/i)).toBeInTheDocument()
    })
  })

  it('shows bootstrap error when current user loading fails', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new TypeError('failed to fetch'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/impossible de joindre l'api laravel/i)).toBeInTheDocument()
    })
  })

  it('shows application bootstrap error for unexpected failures', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new Error('bootstrap failed'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByText(/impossible de charger l'application/i)).toBeInTheDocument()
    })
  })

  it('shows login error when credentials are rejected', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue(null)
    vi.mocked(login).mockRejectedValue(new Error('invalid credentials'))

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user, { password: 'wrong-password' })

    await waitFor(() => {
      expect(screen.getByText(/connexion impossible/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /déconnexion/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnexion/i }))

    await waitFor(() => {
      expect(screen.getByText(/déconnexion impossible/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /déconnecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnecter quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/déconnexion quickbooks impossible/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /connecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connecter quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /redirection/i })).toBeDisabled()
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
      expect(screen.getByRole('button', { name: /déconnecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnecter quickbooks/i }))

    await waitFor(() => {
      expect(disconnectQuickBooks).toHaveBeenCalled()
      expect(screen.getByText(/non connecté/i)).toBeInTheDocument()
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
      expect(screen.getByText(/impossible de charger l'application/i)).toBeInTheDocument()
      expect(screen.queryByRole('button', { name: /déconnecter quickbooks/i })).not.toBeInTheDocument()
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
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await fillLoginForm(user)

    await waitFor(() => {
      expect(fetchQuickBooksStatus).toHaveBeenCalled()
      expect(screen.getByText(/connecté \(realm realm-99\)/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /déconnexion/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnexion/i }))

    await waitFor(() => {
      expect(logout).toHaveBeenCalled()
      expect(screen.getByRole('button', { name: /se connecter/i })).toBeInTheDocument()
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
      expect(screen.getByLabelText(/id employé qbo/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/id employé qbo/i), '7')
    await user.type(screen.getByLabelText(/nom employé/i), 'Jane Doe')
    await user.click(screen.getByRole('button', { name: /enregistrer l'employé/i }))

    await waitFor(() => {
      expect(updateQboEmployee).toHaveBeenCalledWith('7', 'Jane Doe')
      expect(screen.getByText(/employé quickbooks enregistré/i)).toBeInTheDocument()
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
      expect(screen.getByLabelText(/id employé qbo/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/id employé qbo/i), '7')
    await user.click(screen.getByRole('button', { name: /enregistrer l'employé/i }))

    await waitFor(() => {
      expect(screen.getByText(/impossible d'enregistrer l'employé quickbooks/i)).toBeInTheDocument()
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
      expect(screen.getByRole('button', { name: /déconnecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnecter quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByText(/quickbooks déconnecté/i)).toBeInTheDocument()
    })
  })
})
