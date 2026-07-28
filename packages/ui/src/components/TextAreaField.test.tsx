/**
 * @file Tests for TextAreaField.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { TextAreaField } from './TextAreaField'

describe('TextAreaField', () => {
  it('renders a labeled textarea and forwards change events', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()

    render(
      <TextAreaField
        label="Description"
        rows={4}
        value="Initial note"
        onChange={onChange}
      />,
    )

    const textarea = screen.getByLabelText('Description')
    expect(textarea).toHaveValue('Initial note')

    await user.clear(textarea)
    await user.type(textarea, 'Updated note')

    expect(onChange).toHaveBeenCalled()
  })
})
