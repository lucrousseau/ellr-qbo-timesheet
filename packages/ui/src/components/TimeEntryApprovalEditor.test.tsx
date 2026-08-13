/**
 * @file Tests for pending time entry approval inline editor.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntryApprovalEditor } from './TimeEntryApprovalEditor'

const entry = {
  id: 'local:12',
  timeEntryId: 12,
  startTime: '2026-07-30T17:00:00Z',
  endTime: '2026-07-30T18:00:00Z',
  durationSeconds: 3600,
  customerRef: '11',
  customerName: 'Acme',
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
  it('saves trimmed refs and clears empty optional fields', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn().mockResolvedValue(undefined)
    const onCancel = vi.fn()

    render(
      <LocaleProvider>
        <TimeEntryApprovalEditor entry={entry} saving={false} onSave={onSave} onCancel={onCancel} />
      </LocaleProvider>,
    )

    await user.clear(screen.getByLabelText('Client'))
    await user.type(screen.getByLabelText('Client'), ' 44 ')
    await user.clear(screen.getByLabelText('Project'))
    await user.clear(screen.getByLabelText('Service'))
    await user.clear(screen.getByLabelText('Description'))
    await user.click(screen.getByLabelText('Billable'))
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith(
        'local:12',
        expect.objectContaining({
          customer_ref: '44',
          project_ref: null,
          item_ref: null,
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
