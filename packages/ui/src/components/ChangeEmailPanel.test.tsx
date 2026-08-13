/**
 * @file Tests for ChangeEmailPanel rendering and form wiring.
 */

import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { ChangeEmailPanel } from './ChangeEmailPanel'

describe('ChangeEmailPanel', () => {
  it('renders email fields and submits through the handler', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())

    render(
      <ChangeEmailPanel
        currentEmail="before@example.com"
        email=""
        currentPassword=""
        saving={false}
        error={null}
        success={null}
        onEmailChange={vi.fn()}
        onCurrentPasswordChange={vi.fn()}
        onSubmit={onSubmit}
      />,
      { wrapper: createLocaleWrapper('en') },
    )

    expect(screen.getByRole('heading', { name: /change email/i })).toBeInTheDocument()
    expect(screen.getByText(/before@example.com/i)).toBeInTheDocument()

    await user.type(screen.getByLabelText(/^new email$/i), 'after@example.com')
    await user.type(screen.getByLabelText(/^current password$/i), 'OldPassword!1')

    const form = screen.getByRole('button', { name: /update email/i }).closest('form')
    expect(form).not.toBeNull()
    fireEvent.submit(form!)
    expect(onSubmit).toHaveBeenCalled()
  })

  it('shows success and error alerts', () => {
    const { rerender } = render(
      <ChangeEmailPanel
        currentEmail="before@example.com"
        email=""
        currentPassword=""
        saving={false}
        error="Wrong password"
        success={null}
        onEmailChange={vi.fn()}
        onCurrentPasswordChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
      { wrapper: createLocaleWrapper('en') },
    )

    expect(screen.getByText('Wrong password')).toBeInTheDocument()

    rerender(
      <ChangeEmailPanel
        currentEmail="after@example.com"
        email=""
        currentPassword=""
        saving={false}
        error={null}
        success="Email address updated. Check your inbox to verify the new address."
        onEmailChange={vi.fn()}
        onCurrentPasswordChange={vi.fn()}
        onSubmit={vi.fn()}
      />,
    )

    expect(
      screen.getByText('Email address updated. Check your inbox to verify the new address.'),
    ).toBeInTheDocument()
  })
})
