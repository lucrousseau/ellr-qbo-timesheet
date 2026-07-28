/**
 * @file Tests for ConfirmDialog.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmDialog } from './ConfirmDialog'

describe('ConfirmDialog', () => {
  it('calls confirm and cancel handlers', async () => {
    const user = userEvent.setup()
    const onConfirm = vi.fn()
    const onCancel = vi.fn()

    render(
      <ConfirmDialog
        open
        title="Disconnect QuickBooks?"
        description="Cached lists will be cleared."
        confirmLabel="Disconnect"
        cancelLabel="Cancel"
        onConfirm={onConfirm}
        onCancel={onCancel}
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalledTimes(1)

    await user.click(screen.getByRole('button', { name: 'Disconnect' }))
    expect(onConfirm).toHaveBeenCalledTimes(1)
  })

  it('ignores duplicate confirm clicks while the action is pending', async () => {
    const user = userEvent.setup()
    let resolveConfirm: () => void = () => {}
    const onConfirm = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          resolveConfirm = resolve
        }),
    )

    render(
      <ConfirmDialog
        open
        title="Disconnect QuickBooks?"
        description="Cached lists will be cleared."
        confirmLabel="Disconnect"
        cancelLabel="Cancel"
        onConfirm={onConfirm}
        onCancel={vi.fn()}
      />,
    )

    const confirmButton = screen.getByRole('button', { name: 'Disconnect' })
    await user.click(confirmButton)
    await user.click(confirmButton)

    expect(onConfirm).toHaveBeenCalledTimes(1)

    resolveConfirm()
  })
})
