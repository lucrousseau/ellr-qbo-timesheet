/**
 * @file Tests for pending time entry approval editor with QBO pickers.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchQboCustomers, fetchQboProjects, fetchQboServices } from '@ellr/api-client'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntryApprovalEditor } from './TimeEntryApprovalEditor'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
    fetchQboServices: vi.fn(),
  }
})

const entry = {
  id: 'local:12',
  timeEntryId: 12,
  startTime: '2026-07-30T17:00:00Z',
  endTime: '2026-07-30T18:00:00Z',
  durationSeconds: 3600,
  customerRef: '11',
  customerName: 'Acme / Website',
  projectRef: '22',
  itemRef: '33',
  serviceName: 'Design',
  description: 'Draft notes',
  isBillable: false,
  billableLocked: false,
  employeeName: 'Bob',
  approvalStatus: 'pending' as const,
}

describe('TimeEntryApprovalEditor', () => {
  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboServices).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Design' }])
  })

  it('shows client, project, and service labels instead of raw refs', () => {
    render(
      <LocaleProvider>
        <TimeEntryApprovalEditor
          entry={entry}
          saving={false}
          onSave={vi.fn()}
          onCancel={vi.fn()}
        />
      </LocaleProvider>,
    )

    expect(screen.getByRole('button', { name: /Acme/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Website/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Design/ })).toBeInTheDocument()
    expect(screen.queryByDisplayValue('11')).not.toBeInTheDocument()
    expect(screen.queryByDisplayValue('33')).not.toBeInTheDocument()
  })

  it('saves picker refs and clears empty optional fields', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn().mockResolvedValue(undefined)
    const onCancel = vi.fn()

    render(
      <LocaleProvider>
        <TimeEntryApprovalEditor entry={entry} saving={false} onSave={onSave} onCancel={onCancel} />
      </LocaleProvider>,
    )

    await user.clear(screen.getByLabelText('Description'))
    await user.click(screen.getByLabelText('Billable'))
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith(
        'local:12',
        expect.objectContaining({
          customer_ref: '11',
          project_ref: '22',
          item_ref: '33',
          description: null,
          is_billable: true,
        }),
      )
    })
  })

  it('cancels without saving', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn()
    const onCancel = vi.fn()

    render(
      <LocaleProvider>
        <TimeEntryApprovalEditor entry={entry} saving={false} onSave={onSave} onCancel={onCancel} />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onCancel).toHaveBeenCalled()
    expect(onSave).not.toHaveBeenCalled()
  })
})
