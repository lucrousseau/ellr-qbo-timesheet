/**
 * @file Tests for the timesheet supervisor approvals panel.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import {
  approveTimeEntry,
  listPendingTimeEntryApprovals,
  rejectTimeEntry,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { TimesheetTimeEntryApprovalsPanel } from './TimesheetTimeEntryApprovalsPanel'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listPendingTimeEntryApprovals: vi.fn(),
    approveTimeEntry: vi.fn(),
    rejectTimeEntry: vi.fn(),
    updatePendingTimeEntryApproval: vi.fn(),
  }
})

describe('TimesheetTimeEntryApprovalsPanel', () => {
  beforeEach(() => {
    vi.mocked(listPendingTimeEntryApprovals).mockReset()
    vi.mocked(approveTimeEntry).mockReset()
    vi.mocked(rejectTimeEntry).mockReset()
  })

  it('shows an empty state when there are no pending approvals', async () => {
    vi.mocked(listPendingTimeEntryApprovals).mockResolvedValue({
      data: [],
      meta: { count: 0, max_results: 10, start_position: 1, truncated: false },
    })

    render(
      <LocaleProvider>
        <TimesheetTimeEntryApprovalsPanel
          enabled
          useAdminRoutes={false}
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText(/no pending time entries/i)).toBeInTheDocument()
    })
  })

  it('lists pending entries and approves one', async () => {
    const user = userEvent.setup()
    const onSuccess = vi.fn()
    vi.mocked(listPendingTimeEntryApprovals).mockResolvedValue({
      data: [
        {
          id: 12,
          list_id: 'local:12',
          user_id: 2,
          employee_name: 'Bob LeMoche',
          start_time: '2026-07-30T17:00:00Z',
          end_time: '2026-07-30T18:00:00Z',
          duration_seconds: 3600,
          is_billable: false,
          status: 'pending',
        },
      ],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: true },
    })
    vi.mocked(approveTimeEntry).mockResolvedValue({
      id: 12,
      list_id: 'local:12',
      user_id: 2,
      start_time: '2026-07-30T17:00:00Z',
      end_time: '2026-07-30T18:00:00Z',
      duration_seconds: 3600,
      is_billable: false,
      status: 'approved',
      qbo_id: '9001',
    })

    render(
      <LocaleProvider>
        <TimesheetTimeEntryApprovalsPanel
          enabled
          useAdminRoutes={false}
          onSuccess={onSuccess}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText('Bob LeMoche')).toBeInTheDocument()
    })
    expect(screen.getByRole('button', { name: 'Load more' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Approve' }))

    await waitFor(() => {
      expect(approveTimeEntry).toHaveBeenCalledWith(12, { admin: false })
      expect(onSuccess).toHaveBeenCalled()
    })
  })

  it('surfaces load errors', async () => {
    vi.mocked(listPendingTimeEntryApprovals).mockRejectedValue(new Error('offline'))

    render(
      <LocaleProvider>
        <TimesheetTimeEntryApprovalsPanel
          enabled
          useAdminRoutes
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText(/unable to review the time entry/i)).toBeInTheDocument()
    })
  })
})
