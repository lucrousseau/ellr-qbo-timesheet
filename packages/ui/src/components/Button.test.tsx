/**
 * @file Tests for Button.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { Button } from './Button'

describe('Button', () => {
  it('renders primary variant and handles clicks', async () => {
    const user = userEvent.setup()
    const onClick = vi.fn()

    render(<Button type="button" onClick={onClick}>Save</Button>)

    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onClick).toHaveBeenCalledOnce()
  })

  it('submits a parent form when type is submit', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())

    render(
      <form onSubmit={onSubmit}>
        <Button type="submit">Save time</Button>
      </form>,
    )

    await user.click(screen.getByRole('button', { name: 'Save time' }))

    expect(onSubmit).toHaveBeenCalledOnce()
  })

  it('does not fire click when disabled', async () => {
    const user = userEvent.setup()
    const onClick = vi.fn()

    render(
      <Button type="button" variant="secondary" disabled onClick={onClick}>
        Cancel
      </Button>,
    )

    await user.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(onClick).not.toHaveBeenCalled()
  })

  it('does not submit a parent form when primary submit is disabled', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn((event) => event.preventDefault())

    render(
      <form onSubmit={onSubmit}>
        <Button type="submit" disabled>Saving</Button>
      </form>,
    )

    await user.click(screen.getByRole('button', { name: 'Saving' }))

    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('renders danger and link variants', () => {
    render(
      <>
        <Button type="button" variant="danger">Delete</Button>
        <Button type="button" variant="link">Back</Button>
      </>,
    )

    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Back' })).toBeInTheDocument()
  })

  it('renders compact secondary size for header chrome', () => {
    render(
      <Button type="button" variant="secondary" size="compact">
        Sign out
      </Button>,
    )

    expect(screen.getByRole('button', { name: 'Sign out' })).toHaveClass('px-3', 'py-2')
  })
})
