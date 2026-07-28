/**
 * @file Tests for TabNav accessibility and keyboard interaction.
 */

import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { TabNav } from './TabNav'

describe('TabNav', () => {
  it('renders a tablist with linked tab panels', () => {
    render(
      <TabNav
        items={[
          { id: 'preferences', label: 'Preferences' },
          { id: 'administrator', label: 'Administrator' },
        ]}
        activeId="preferences"
        onChange={vi.fn()}
        ariaLabel="Admin sections"
        idPrefix="admin"
      />,
    )

    expect(screen.getByRole('navigation', { name: 'Admin sections' })).toBeInTheDocument()
    expect(screen.getByRole('tablist')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Preferences' })).toHaveAttribute(
      'aria-controls',
      'admin-panel-preferences',
    )
    expect(screen.getByRole('tab', { name: 'Administrator' })).toHaveAttribute(
      'aria-controls',
      'admin-panel-administrator',
    )
  })

  it('selects the next tab when pressing ArrowRight', async () => {
    const user = userEvent.setup()
    const onChange = vi.fn()

    render(
      <TabNav
        items={[
          { id: 'preferences', label: 'Preferences' },
          { id: 'administrator', label: 'Administrator' },
        ]}
        activeId="preferences"
        onChange={onChange}
        ariaLabel="Admin sections"
      />,
    )

    const navigation = screen.getByRole('navigation', { name: 'Admin sections' })
    const preferencesTab = within(navigation).getByRole('tab', { name: 'Preferences' })
    preferencesTab.focus()
    await user.keyboard('{ArrowRight}')

    expect(onChange).toHaveBeenCalledWith('administrator')
  })
})
