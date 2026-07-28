/**
 * @file Tests for TextField.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { TextField } from './TextField'

describe('TextField', () => {
  it('renders a labeled input and forwards change events', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()

    render(
      <TextField
        label="Email"
        type="email"
        value="user@example.com"
        onChange={onChange}
      />,
    )

    const input = screen.getByLabelText('Email')
    expect(input).toHaveValue('user@example.com')

    await user.clear(input)
    await user.type(input, 'admin@example.com')

    expect(onChange).toHaveBeenCalled()
  })
})
