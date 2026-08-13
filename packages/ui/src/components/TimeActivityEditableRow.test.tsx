/**
 * @file Tests for administrator editable time activity rows.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeActivityEditableRow } from './TimeActivityEditableRow'

const entry = {
  id: '101',
  startTime: '2026-07-29T09:00:00',
  endTime: '2026-07-29T11:00:00',
  durationSeconds: 7200,
  customerName: 'Acme Corp',
  serviceName: 'Programming',
  description: 'Support',
  isBillable: true,
  billableLocked: false,
}

describe('TimeActivityEditableRow', () => {
  it('saves dirty fields and clears blank descriptions', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <table>
          <tbody>
            <TimeActivityEditableRow entry={entry} saving={false} onSave={onSave} />
          </tbody>
        </table>
      </LocaleProvider>,
    )

    const saveButton = screen.getByRole('button', { name: 'Save' })
    expect(saveButton).toBeDisabled()

    await user.clear(screen.getByLabelText('Description'))
    await user.click(screen.getByLabelText('Billable'))
    expect(saveButton).toBeEnabled()

    await user.click(saveButton)

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith(
        '101',
        expect.objectContaining({
          description: null,
          is_billable: false,
        }),
      )
    })
  })

  it('shows billed lock messaging when billable is locked', () => {
    render(
      <LocaleProvider>
        <table>
          <tbody>
            <TimeActivityEditableRow
              entry={{ ...entry, billableLocked: true }}
              saving
              onSave={vi.fn()}
            />
          </tbody>
        </table>
      </LocaleProvider>,
    )

    expect(screen.getByText(/already billed/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled()
  })
})
