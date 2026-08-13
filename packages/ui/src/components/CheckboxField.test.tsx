/**
 * @file Tests for CheckboxField.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { CheckboxField } from './CheckboxField'

describe('CheckboxField', () => {
  it('renders a labeled checkbox and forwards change events', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()

    render(<CheckboxField label="Billable" checked={false} onChange={onChange} />)

    await user.click(screen.getByRole('checkbox', { name: 'Billable' }))

    expect(onChange).toHaveBeenCalledWith(true)
  })

  it('keeps a visually hidden label for compact table checkboxes', () => {
    render(
      <CheckboxField
        label="Select draft entry"
        labelVisuallyHidden
        checked={false}
        onChange={vi.fn()}
      />,
    )

    expect(screen.getByRole('checkbox', { name: 'Select draft entry' })).toBeInTheDocument()
    expect(screen.getByText('Select draft entry')).toHaveClass('sr-only')
  })
})
