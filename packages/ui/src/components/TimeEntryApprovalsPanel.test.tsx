/**
 * @file Tests for the shared pending time entry approvals panel.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntryApprovalsPanel } from './TimeEntryApprovalsPanel'

const listPendingTimeEntryApprovals = vi.hoisted(() => vi.fn())
const approveTimeEntry = vi.hoisted(() => vi.fn())
const rejectTimeEntry = vi.hoisted(() => vi.fn())
const returnTimeEntryToDraft = vi.hoisted(() => vi.fn())
const updatePendingTimeEntryApproval = vi.hoisted(() => vi.fn())

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')
  return {
    ...actual,
    listPendingTimeEntryApprovals,
    approveTimeEntry,
    rejectTimeEntry,
    returnTimeEntryToDraft,
    updatePendingTimeEntryApproval,
  }
})

const pendingEntry = {
  id: 12,
  user_id: 3,
  employee_name: 'Bob LeMoche',
  customer_ref: '11',
  customer_name: "Bill's Windsurf Shop",
  project_ref: null,
  project_name: null,
  item_ref: '9',
  item_name: 'Design',
  start_time: '2026-07-30T17:22:00Z',
  end_time: '2026-07-30T18:24:00Z',
  duration_seconds: 3720,
  description: 'Initial',
  is_billable: false,
  status: 'pending' as const,
  qbo_id: null,
  list_id: 'local:12',
}

describe('TimeEntryApprovalsPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listPendingTimeEntryApprovals.mockResolvedValue({
      data: [pendingEntry],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    })
    approveTimeEntry.mockResolvedValue({ data: pendingEntry })
    updatePendingTimeEntryApproval.mockResolvedValue({
      data: { ...pendingEntry, description: 'Updated' },
    })
  })

  it('renders the shared table with icon review actions', async () => {
    const user = userEvent.setup()
    const onSuccess = vi.fn()

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel
          enabled
          title="Pending time entry approvals"
          help="Review entries"
          emptyMessage="Nothing pending"
          onSuccess={onSuccess}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(await screen.findByText('Bob LeMoche')).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Employee' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reject' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Return to draft' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit entry' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Approve' }))
    await waitFor(() => {
      expect(approveTimeEntry).toHaveBeenCalled()
    })
  })

  it('opens the pending entry editor dialog and saves updates', async () => {
    const user = userEvent.setup()

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel
          enabled
          title="Pending time entry approvals"
          help="Review entries"
          emptyMessage="Nothing pending"
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(await screen.findByText('Bob LeMoche')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Edit entry' }))
    expect(await screen.findByLabelText('Description')).toBeInTheDocument()
    await user.clear(screen.getByLabelText('Description'))
    await user.type(screen.getByLabelText('Description'), 'Updated')
    await user.click(screen.getByRole('button', { name: /save/i }))

    await waitFor(() => {
      expect(updatePendingTimeEntryApproval).toHaveBeenCalled()
    })
  })

  it('filters pending rows by employee', async () => {
    const user = userEvent.setup()
    listPendingTimeEntryApprovals.mockResolvedValue({
      data: [
        pendingEntry,
        {
          ...pendingEntry,
          id: 13,
          list_id: 'local:13',
          employee_name: 'Jane Doe',
          customer_name: 'Acme Corp',
          is_billable: true,
        },
      ],
      meta: { count: 2, max_results: 10, start_position: 1, truncated: false },
    })

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel
          enabled
          title="Pending time entry approvals"
          help="Review entries"
          emptyMessage="Nothing pending"
          onSuccess={vi.fn()}
          onError={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(await screen.findByText('Bob LeMoche')).toBeInTheDocument()
    expect(screen.getByText('Jane Doe')).toBeInTheDocument()
    expect(screen.getByLabelText('Employee')).toBeInTheDocument()

    await user.click(screen.getByLabelText('Employee'))
    await user.click(screen.getByRole('option', { name: 'Jane Doe' }))

    expect(screen.getAllByText('Jane Doe').length).toBeGreaterThan(0)
    expect(screen.queryByText('Bob LeMoche')).not.toBeInTheDocument()
  })
})
