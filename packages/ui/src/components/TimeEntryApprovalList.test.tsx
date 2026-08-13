/**
 * @file Tests for the pending time entry approval list.
 */

import { render, screen } from '@testing-library/react'
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
    expect(onApprove).toHaveBeenCalledWith('local:12', { groupForQbo: false })
  })
})
