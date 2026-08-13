/**
 * @file Tests for approve, reject, and return-to-draft action controls.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'

describe('TimeEntryApprovalActions', () => {
  it('approves an entry from the default action row', async () => {
    const user = userEvent.setup()
    const onApprove = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalActions
          entryId="local:12"
          reviewing={false}
          onApprove={onApprove}
          onReject={vi.fn()}
          onReturnToDraft={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Approve' }))
    expect(onApprove).toHaveBeenCalledWith('local:12')
  })

  it('returns an entry to draft from the default action row', async () => {
    const user = userEvent.setup()
    const onReturnToDraft = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalActions
          entryId="local:12"
          reviewing={false}
          onApprove={vi.fn()}
          onReject={vi.fn()}
          onReturnToDraft={onReturnToDraft}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Return to draft' }))
    expect(onReturnToDraft).toHaveBeenCalledWith('local:12')
  })

  it('submits a rejection reason from the inline form', async () => {
    const user = userEvent.setup()
    const onReject = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalActions
          entryId="local:12"
          reviewing={false}
          onApprove={vi.fn()}
          onReject={onReject}
          onReturnToDraft={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Reject' }))
    await user.type(screen.getByLabelText(/reason/i), 'Missing notes')
    await user.click(screen.getByRole('button', { name: /^reject$/i }))

    expect(onReject).toHaveBeenCalledWith('local:12', 'Missing notes')
  })

  it('cancels the reject form without submitting', async () => {
    const user = userEvent.setup()

    render(
      <LocaleProvider>
        <TimeEntryApprovalActions
          entryId="local:12"
          reviewing={false}
          onApprove={vi.fn()}
          onReject={vi.fn()}
          onReturnToDraft={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Reject' }))
    await user.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
  })

  it('submits a null rejection reason when the field is blank', async () => {
    const user = userEvent.setup()
    const onReject = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalActions
          entryId="local:12"
          reviewing={false}
          onApprove={vi.fn()}
          onReject={onReject}
          onReturnToDraft={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Reject' }))
    await user.click(screen.getByRole('button', { name: /^reject$/i }))

    expect(onReject).toHaveBeenCalledWith('local:12', null)
  })
})
