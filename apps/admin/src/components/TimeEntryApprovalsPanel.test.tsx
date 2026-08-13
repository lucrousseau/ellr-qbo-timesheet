/**
 * @file Tests for administrator time entry approvals panel.
 */

import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '@ellr/ui'
import { listPendingTimeEntryApprovals } from '@ellr/api-client'
import { TimeEntryApprovalsPanel } from './TimeEntryApprovalsPanel'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listPendingTimeEntryApprovals: vi.fn(),
    approveTimeEntry: vi.fn(),
    rejectTimeEntry: vi.fn(),
    returnTimeEntryToDraft: vi.fn(),
    updatePendingTimeEntryApproval: vi.fn(),
  }
})

describe('TimeEntryApprovalsPanel', () => {
  beforeEach(() => {
    vi.mocked(listPendingTimeEntryApprovals).mockReset()
  })

  it('renders pending entries in the shared table', async () => {
    vi.mocked(listPendingTimeEntryApprovals).mockResolvedValue({
      data: [
        {
          id: 12,
          list_id: 'local:12',
          user_id: 2,
          employee_name: 'Jane Doe',
          customer_name: 'Acme Corp',
          item_name: 'Design',
          start_time: '2026-07-30T17:00:00Z',
          end_time: '2026-07-30T18:00:00Z',
          duration_seconds: 3600,
          is_billable: false,
          status: 'pending',
        },
      ],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    })

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel enabled onSuccess={vi.fn()} onError={vi.fn()} />
      </LocaleProvider>,
    )

    expect(await screen.findByText('Jane Doe')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Return to draft' })).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Employee' })).toBeInTheDocument()
  })

  it('shows an empty state when there are no pending entries', async () => {
    vi.mocked(listPendingTimeEntryApprovals).mockResolvedValue({
      data: [],
      meta: { count: 0, max_results: 10, start_position: 1, truncated: false },
    })

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel enabled onSuccess={vi.fn()} onError={vi.fn()} />
      </LocaleProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText(/no pending time entries/i)).toBeInTheDocument()
    })
  })
})
