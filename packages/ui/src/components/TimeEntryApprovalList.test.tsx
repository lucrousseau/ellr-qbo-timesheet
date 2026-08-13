/**
 * @file Tests for the pending time entry approval list.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntryApprovalList } from './TimeEntryApprovalList'

describe('TimeEntryApprovalList', () => {
  it('renders approve and reject actions for each pending entry', async () => {
    const user = userEvent.setup()
    const onApprove = vi.fn().mockResolvedValue(undefined)
    const onReject = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalList
          entries={[
            {
              id: 'local:12',
              timeEntryId: 12,
              startTime: '2026-07-30T17:22:00Z',
              endTime: '2026-07-30T18:24:00Z',
              durationSeconds: 3720,
              customerName: "Bill's Windsurf Shop",
              serviceName: 'Design',
              description: null,
              isBillable: false,
              billableLocked: false,
              employeeName: 'Bob LeMoche',
            },
          ]}
          reviewingId={null}
          onApprove={onApprove}
          onReject={onReject}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Bob LeMoche')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Approve' }))
    expect(onApprove).toHaveBeenCalledWith('local:12')
  })

  it('opens the pending entry editor and saves updates', async () => {
    const user = userEvent.setup()
    const onUpdateEntry = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeEntryApprovalList
          entries={[
            {
              id: 'local:12',
              timeEntryId: 12,
              startTime: '2026-07-30T17:22:00Z',
              endTime: '2026-07-30T18:24:00Z',
              durationSeconds: 3720,
              customerRef: '11',
              customerName: "Bill's Windsurf Shop",
              serviceName: 'Design',
              description: 'Initial',
              isBillable: false,
              billableLocked: false,
              employeeName: 'Bob LeMoche',
              approvalStatus: 'pending',
            },
          ]}
          reviewingId={null}
          onApprove={vi.fn()}
          onReject={vi.fn()}
          onUpdateEntry={onUpdateEntry}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Edit entry' }))
    await user.clear(screen.getByLabelText('Description'))
    await user.type(screen.getByLabelText('Description'), 'Updated notes')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => {
      expect(onUpdateEntry).toHaveBeenCalledWith(
        'local:12',
        expect.objectContaining({ description: 'Updated notes' }),
      )
    })
  })
})
