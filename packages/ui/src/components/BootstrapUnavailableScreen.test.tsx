/**
 * @file Tests for BootstrapUnavailableScreen.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { BootstrapUnavailableScreen } from './BootstrapUnavailableScreen'

describe('BootstrapUnavailableScreen', () => {
  it('renders reconnect copy and calls onRetry', async () => {
    const user = userEvent.setup()
    const onRetry = vi.fn()
    const wrapper = createLocaleWrapper()

    render(<BootstrapUnavailableScreen onRetry={onRetry} />, { wrapper })

    expect(screen.getByRole('heading', { name: /reconnecting to the server/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /try again/i })).toBeEnabled()

    await user.click(screen.getByRole('button', { name: /try again/i }))

    expect(onRetry).toHaveBeenCalledOnce()
  })

  it('shows retrying state and disables the button', () => {
    const wrapper = createLocaleWrapper()

    render(<BootstrapUnavailableScreen onRetry={vi.fn()} retrying />, { wrapper })

    expect(screen.getByRole('button', { name: /retrying/i })).toBeDisabled()
  })

  it('renders french copy when locale is fr', () => {
    const wrapper = createLocaleWrapper('fr')

    render(<BootstrapUnavailableScreen onRetry={vi.fn()} />, { wrapper })

    expect(screen.getByRole('heading', { name: /reconnexion au serveur/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /réessayer/i })).toBeInTheDocument()
  })
})
