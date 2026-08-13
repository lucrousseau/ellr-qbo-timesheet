/**
 * @file Tests for the draft time entry edit dialog.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import { fetchQboCustomers, fetchQboProjects, fetchQboServices } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DraftTimeEntryEditDialog } from './DraftTimeEntryEditDialog'

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
  startTime: '2026-07-30T09:00:00',
  endTime: '2026-07-30T10:00:00',
  durationSeconds: 3600,
  customerRef: '11',
  customerName: 'Acme',
  projectRef: '22',
  itemRef: '33',
  serviceName: 'Design',
  description: 'Notes',
  isBillable: false,
  billableLocked: false,
  approvalStatus: 'draft' as const,
}

describe('DraftTimeEntryEditDialog', () => {
  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboServices).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Design' }])
  })

  it('saves edited draft fields', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn().mockResolvedValue(undefined)
    const onClose = vi.fn()

    render(
      <LocaleProvider>
        <DraftTimeEntryEditDialog entry={entry} saving={false} onClose={onClose} onSave={onSave} />
      </LocaleProvider>,
    )

    expect(screen.getByRole('heading', { name: /edit time entry/i })).toBeInTheDocument()

    await user.clear(screen.getByLabelText('Description'))
    await user.type(screen.getByLabelText('Description'), 'Updated notes')
    await user.click(screen.getByLabelText('Billable'))
    await user.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith(
        'local:12',
        expect.objectContaining({
          description: 'Updated notes',
          is_billable: true,
          customer_ref: '11',
          project_ref: '22',
          item_ref: '33',
        }),
      )
    })
  })

  it('closes without saving', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn()
    const onClose = vi.fn()

    render(
      <LocaleProvider>
        <DraftTimeEntryEditDialog entry={entry} saving={false} onClose={onClose} onSave={onSave} />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onClose).toHaveBeenCalled()
    expect(onSave).not.toHaveBeenCalled()
  })

  it('loads client options when the client picker opens', async () => {
    const user = userEvent.setup()

    render(
      <LocaleProvider>
        <DraftTimeEntryEditDialog
          entry={entry}
          saving={false}
          onClose={vi.fn()}
          onSave={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByLabelText(/^client$/i))

    await waitFor(() => {
      expect(fetchQboCustomers).toHaveBeenCalled()
    })
  })
})
