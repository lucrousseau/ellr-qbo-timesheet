/**
 * @file Tests for PasswordField.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { PasswordField } from './PasswordField'

describe('PasswordField', () => {
  it('toggles password visibility with an accessible button', async () => {
    const user = userEvent.setup()

    render(
      <LocaleProvider>
        <PasswordField label="Password" value="secret" onChange={() => undefined} />
      </LocaleProvider>,
    )

    const input = screen.getByLabelText('Password')
    expect(input).toHaveAttribute('type', 'password')

    const showButton = screen.getByRole('button', { name: 'Show password' })
    await user.click(showButton)

    expect(input).toHaveAttribute('type', 'text')
    expect(showButton).toHaveAttribute('aria-pressed', 'true')

    const hideButton = screen.getByRole('button', { name: 'Hide password' })
    await user.click(hideButton)

    expect(input).toHaveAttribute('type', 'password')
    expect(hideButton).toHaveAttribute('aria-pressed', 'false')
  })
})
