/**
 * @file Smoke tests for shared auth and layout screens.
 */

import type { ReactElement } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { AppShell } from './AppShell'
import { ForgotPasswordForm } from './ForgotPasswordForm'
import { LoadingScreen } from './LoadingScreen'
import { LoginForm } from './LoginForm'
import { ResetPasswordForm } from './ResetPasswordForm'
import { UserPreferencesPanel } from './UserPreferencesPanel'

/**
 * Renders a component inside LocaleProvider for screen tests.
 * @param ui Component tree to render.
 * @param locale Initial locale for the provider.
 * @returns Testing-library render result.
 */
function renderWithLocale(ui: ReactElement, locale: 'en' | 'fr' = 'en') {
  return render(<LocaleProvider initialLocale={locale}>{ui}</LocaleProvider>)
}

describe('auth and layout screens', () => {
  it('renders the login form with error and footer content', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())
    const onEmailChange = vi.fn()
    const onPasswordChange = vi.fn()

    renderWithLocale(
      <LoginForm
        title="Timesheet"
        subtitle="Sign in to record your time"
        email="user@example.com"
        password="secret"
        onEmailChange={onEmailChange}
        onPasswordChange={onPasswordChange}
        onSubmit={onSubmit}
        error="Invalid email or password."
        footer={<p>Need help?</p>}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Timesheet' })).toBeInTheDocument()
    expect(screen.getByText('ellr')).toBeInTheDocument()
    expect(screen.getByText('Technology + accounting expertise')).toBeInTheDocument()
    expect(screen.getByText('Invalid email or password.')).toBeInTheDocument()
    expect(screen.getByText('Need help?')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Sign in' }))
    expect(onSubmit).toHaveBeenCalled()
  })

  it('renders forgot-password success and error states', async () => {
    const user = userEvent.setup()
    const onBackToLogin = vi.fn()

    const { rerender } = renderWithLocale(
      <ForgotPasswordForm
        title="Admin"
        email="user@example.com"
        submitting={false}
        error="Unable to send the reset link."
        success={null}
        onEmailChange={vi.fn()}
        onSubmit={vi.fn((event) => event.preventDefault())}
        onBackToLogin={onBackToLogin}
      />,
    )

    expect(screen.getByText('Unable to send the reset link.')).toBeInTheDocument()

    rerender(
      <LocaleProvider>
        <ForgotPasswordForm
          title="Admin"
          email="user@example.com"
          submitting
          error={null}
          success="If that email exists, a reset link has been sent."
          onEmailChange={vi.fn()}
          onSubmit={vi.fn()}
          onBackToLogin={onBackToLogin}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('If that email exists, a reset link has been sent.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sending…' })).toBeDisabled()

    await user.click(screen.getByRole('button', { name: 'Back to sign in' }))
    expect(onBackToLogin).toHaveBeenCalled()
  })

  it('renders reset-password states including invalid links', () => {
    renderWithLocale(
      <ResetPasswordForm
        title="Admin"
        email="user@example.com"
        password=""
        passwordConfirmation=""
        submitting={false}
        error={null}
        success={null}
        invalidLink
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={vi.fn()}
        onBackToLogin={vi.fn()}
      />,
    )

    expect(screen.getByText('This reset link is invalid or incomplete.')).toBeInTheDocument()
  })

  it('renders the reset-password form with policy requirements', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())

    renderWithLocale(
      <ResetPasswordForm
        title="Admin"
        email="user@example.com"
        password="EllrNew!2026"
        passwordConfirmation="EllrNew!2026"
        submitting={false}
        error={null}
        success={null}
        invalidLink={false}
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={onSubmit}
        onBackToLogin={vi.fn()}
      />,
    )

    expect(screen.getByText(/Resetting password for user@example.com/)).toBeInTheDocument()
    expect(screen.getAllByText(/At least/).length).toBeGreaterThan(0)

    await user.click(screen.getByRole('button', { name: 'Update password' }))
    expect(onSubmit).toHaveBeenCalled()
  })

  it('renders reset-password success and error banners', () => {
    renderWithLocale(
      <ResetPasswordForm
        title="Admin"
        email="user@example.com"
        password=""
        passwordConfirmation=""
        submitting={false}
        error="Unable to reset the password."
        success="Password updated. You can sign in now."
        invalidLink={false}
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={vi.fn()}
        onBackToLogin={vi.fn()}
      />,
    )

    expect(screen.getByText('Unable to reset the password.')).toBeInTheDocument()
    expect(screen.getByText('Password updated. You can sign in now.')).toBeInTheDocument()
  })

  it('renders the authenticated shell and loading screen', async () => {
    const user = userEvent.setup()
    const onLogout = vi.fn()

    renderWithLocale(
      <AppShell title="Admin" userEmail="user@example.com" onLogout={onLogout}>
        <p>Dashboard content</p>
      </AppShell>,
    )

    expect(screen.getByText('Dashboard content')).toBeInTheDocument()
    expect(screen.getByText('ellr')).toBeInTheDocument()
    expect(screen.getByText(/Signed in as user@example.com/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /contact support/i })).toHaveAttribute(
      'href',
      'mailto:hello@ellr.ca?subject=Ellr%20Timesheet%20support',
    )

    await user.click(screen.getByRole('button', { name: 'Sign out' }))
    expect(onLogout).toHaveBeenCalled()

    renderWithLocale(<LoadingScreen />)
    expect(screen.getByText('Loading...')).toBeInTheDocument()
  })

  it('renders the preferences panel and submits locale changes', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn((event) => event.preventDefault())
    const onLocaleChange = vi.fn()

    renderWithLocale(
      <UserPreferencesPanel
        locale="en"
        timezone="America/Toronto"
        companyTimezone="America/Toronto"
        saving={false}
        onLocaleChange={onLocaleChange}
        onTimezoneChange={vi.fn()}
        onSave={onSave}
      />,
    )

    await user.click(screen.getByLabelText(/language/i))
    await user.click(screen.getByRole('option', { name: /french/i }))
    expect(onLocaleChange).toHaveBeenCalledWith('fr')

    await user.click(screen.getByRole('button', { name: 'Save' }))
    expect(onSave).toHaveBeenCalled()
  })
})
