/**
 * @file Tests for ChangePasswordPanel rendering and form wiring.
 */

import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { ChangePasswordPanel } from './ChangePasswordPanel'

describe('ChangePasswordPanel', () => {
  it('renders password fields and submits through the handler', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())

    render(
      <ChangePasswordPanel
        currentPassword=""
        password=""
        passwordConfirmation=""
        saving={false}
        error={null}
        success={null}
        onCurrentPasswordChange={vi.fn()}
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={onSubmit}
      />,
      { wrapper: createLocaleWrapper('en') },
    )

    expect(screen.getByRole('heading', { name: /change password/i })).toBeInTheDocument()

    await user.type(screen.getByLabelText(/current password/i), 'OldPassword!1')
    await user.type(screen.getByLabelText(/^new password$/i), 'NewPassword!2')
    await user.type(screen.getByLabelText(/confirm password/i), 'NewPassword!2')

    const form = screen.getByRole('button', { name: /update password/i }).closest('form')
    expect(form).not.toBeNull()
    fireEvent.submit(form!)
    expect(onSubmit).toHaveBeenCalled()
  })

  it('shows success and error alerts', () => {
    const { rerender } = render(
      <ChangePasswordPanel
        currentPassword=""
        password=""
        passwordConfirmation=""
        saving={false}
        error="Wrong password"
        success={null}
        onCurrentPasswordChange={vi.fn()}
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
      { wrapper: createLocaleWrapper('en') },
    )

    expect(screen.getByText('Wrong password')).toBeInTheDocument()

    rerender(
      <ChangePasswordPanel
        currentPassword=""
        password=""
        passwordConfirmation=""
        saving={false}
        error={null}
        success="Password updated."
        onCurrentPasswordChange={vi.fn()}
        onPasswordChange={vi.fn()}
        onPasswordConfirmationChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
    )

    expect(screen.getByText('Password updated.')).toBeInTheDocument()
  })
})
