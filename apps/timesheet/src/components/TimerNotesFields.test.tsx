/**
 * @file Tests for timer ticket, description, and billable fields.
 */

import { LocaleProvider } from '@ellr/ui'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { TimerNotesFields } from './TimerNotesFields'

describe('TimerNotesFields', () => {
  it('forwards ticket, description, and billable changes', async () => {
    const user = userEvent.setup()
    const onTicketKeyChange = vi.fn()
    const onTicketKeyBlur = vi.fn()
    const onDescriptionChange = vi.fn()
    const onDescriptionBlur = vi.fn()
    const onBillableChange = vi.fn()

    render(
      <LocaleProvider>
        <TimerNotesFields
          ticketKey=""
          description=""
          isBillable={false}
          onTicketKeyChange={onTicketKeyChange}
          onTicketKeyBlur={onTicketKeyBlur}
          onDescriptionChange={onDescriptionChange}
          onDescriptionBlur={onDescriptionBlur}
          onBillableChange={onBillableChange}
        />
      </LocaleProvider>,
    )

    await user.type(screen.getByLabelText('Ticket'), 'ELLR-1')
    expect(onTicketKeyChange).toHaveBeenCalled()

    await user.type(screen.getByLabelText('Description'), 'Fix login')
    expect(onDescriptionChange).toHaveBeenCalled()

    await user.click(screen.getByLabelText('Billable'))
    expect(onBillableChange).toHaveBeenCalledWith(true)
  })
})
