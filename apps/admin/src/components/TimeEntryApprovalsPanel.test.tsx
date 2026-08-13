/**
 * @file Tests for administrator time entry approvals panel.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider, useTimeEntryApprovals } from '@ellr/ui'
import { TimeEntryApprovalsPanel } from './TimeEntryApprovalsPanel'

vi.mock('@ellr/ui', async () => {
  const actual = await vi.importActual<typeof import('@ellr/ui')>('@ellr/ui')

  return {
    ...actual,
    useTimeEntryApprovals: vi.fn(),
  }
})

describe('TimeEntryApprovalsPanel', () => {
  it('renders pending entries from the approvals hook', () => {
    vi.mocked(useTimeEntryApprovals).mockReturnValue({
      entries: [
        {
          id: 'local:12',
          timeEntryId: 12,
          startTime: '2026-07-30T17:00:00Z',
          endTime: '2026-07-30T18:00:00Z',
          durationSeconds: 3600,
          customerName: 'Acme Corp',
          serviceName: 'Design',
          description: null,
          isBillable: false,
          billableLocked: false,
          employeeName: 'Jane Doe',
        },
      ],
      loading: false,
      loadingMore: false,
      hasMore: false,
      error: null,
      reviewingId: null,
      loadMore: vi.fn(),
      approveEntry: vi.fn(),
      rejectEntry: vi.fn(),
      updateEntry: vi.fn(),
    })

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel enabled onSuccess={vi.fn()} onError={vi.fn()} />
      </LocaleProvider>,
    )

    expect(screen.getByText('Jane Doe')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
  })

  it('shows an empty state when there are no pending entries', () => {
    vi.mocked(useTimeEntryApprovals).mockReturnValue({
      entries: [],
      loading: false,
      loadingMore: false,
      hasMore: false,
      error: null,
      reviewingId: null,
      loadMore: vi.fn(),
      approveEntry: vi.fn(),
      rejectEntry: vi.fn(),
      updateEntry: vi.fn(),
    })

    render(
      <LocaleProvider>
        <TimeEntryApprovalsPanel enabled onSuccess={vi.fn()} onError={vi.fn()} />
      </LocaleProvider>,
    )

    expect(screen.getByText(/no pending time entries/i)).toBeInTheDocument()
  })
})
