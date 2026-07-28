/**
 * @file Tests for static listbox select.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { StaticSelect } from './StaticSelect'

describe('StaticSelect', () => {
  it('opens the list and selects an option', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()

    render(
      <StaticSelect
        label="Language"
        value="en"
        options={['en', 'fr'] as const}
        onChange={onChange}
        getOptionValue={(locale) => locale}
        getOptionLabel={(locale) => (locale === 'en' ? 'English' : 'French')}
      />,
    )

    await user.click(screen.getByLabelText(/language/i))
    await user.click(screen.getByRole('option', { name: 'French' }))

    expect(onChange).toHaveBeenCalledWith('fr')
  })
})
