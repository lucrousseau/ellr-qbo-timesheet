import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { apiFetch, fetchCurrentUser, login, logout } from '@ellr/api-client'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')
  return {
    ...actual,
    apiFetch: vi.fn(),
    fetchCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
  }
})

describe('Admin App', () => {
  const originalLocation = window.location

  beforeEach(() => {
    vi.mocked(apiFetch).mockReset()
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(login).mockReset()
    vi.mocked(logout).mockReset()
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
    vi.mocked(apiFetch).mockResolvedValue({
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
    vi.mocked(apiFetch).mockResolvedValue({
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
    vi.mocked(apiFetch).mockImplementation(async (path: string) => {
      if (path === '/quickbooks/status') {
        return { connected: false }
      }
      return { authorization_url: 'https://intuit.example/oauth' }
    })

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
    vi.mocked(apiFetch).mockImplementation(async (path: string) => {
      if (path === '/quickbooks/status') {
        return { connected: false }
      }
      throw new Error('offline')
    })

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
    vi.mocked(apiFetch).mockResolvedValue({ connected: false })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/courriel/i), 'test@example.com')
    await user.type(screen.getByLabelText(/mot de passe/i), 'password')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

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
    vi.mocked(apiFetch).mockResolvedValue({ connected: true, realm_id: 'realm-42' })

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

    await user.type(screen.getByLabelText(/courriel/i), 'test@example.com')
    await user.type(screen.getByLabelText(/mot de passe/i), 'wrong-password')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

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
    vi.mocked(apiFetch).mockResolvedValue({ connected: false })
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
    vi.mocked(apiFetch).mockImplementation(async (path: string) => {
      if (path === '/quickbooks/status') {
        return { connected: true, realm_id: 'realm-42' }
      }
      if (path === '/quickbooks/disconnect') {
        throw new Error('disconnect failed')
      }
      return {}
    })

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
    vi.mocked(apiFetch).mockImplementation(async (path: string) => {
      if (path === '/quickbooks/status') {
        return { connected: false }
      }
      return new Promise(() => {})
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /connecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /connecter quickbooks/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /redirection/i })).toBeDisabled()
    })
  })

  it('calls quickbooks disconnect with post', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(apiFetch).mockImplementation(async (path: string, init?: RequestInit) => {
      if (path === '/quickbooks/status') {
        return { connected: true, realm_id: 'realm-42' }
      }
      if (path === '/quickbooks/disconnect' && init?.method === 'POST') {
        return { connected: false }
      }
      return {}
    })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /déconnecter quickbooks/i })).toBeInTheDocument()
    })

    await user.click(screen.getByRole('button', { name: /déconnecter quickbooks/i }))

    await waitFor(() => {
      expect(apiFetch).toHaveBeenCalledWith('/quickbooks/disconnect', { method: 'POST' })
      expect(screen.getByText(/non connecté/i)).toBeInTheDocument()
    })
  })

  it('hides disconnect button when quickbooks status is unavailable', async () => {
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(apiFetch).mockRejectedValue(new Error('status unavailable'))

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
    vi.mocked(apiFetch).mockResolvedValue({ connected: true, realm_id: 'realm-99' })

    render(<App />)

    await waitFor(() => {
      expect(screen.getByLabelText(/courriel/i)).toBeInTheDocument()
    })

    await user.type(screen.getByLabelText(/courriel/i), 'test@example.com')
    await user.type(screen.getByLabelText(/mot de passe/i), 'password')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    await waitFor(() => {
      expect(apiFetch).toHaveBeenCalledWith('/quickbooks/status')
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
    vi.mocked(apiFetch).mockResolvedValue({ connected: false })

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

  it('disconnects quickbooks from the admin app', async () => {
    const user = userEvent.setup()
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
    })
    vi.mocked(apiFetch).mockImplementation(async (path: string, init?: RequestInit) => {
      if (path === '/quickbooks/status') {
        return { connected: true, realm_id: 'realm-42' }
      }
      if (path === '/quickbooks/disconnect' && init?.method === 'POST') {
        return { connected: false }
      }
      return {}
    })

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
