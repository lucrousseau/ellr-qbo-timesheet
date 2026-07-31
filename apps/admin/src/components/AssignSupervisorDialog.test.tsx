/**
 * @file Tests for supervisor assignment dialog.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import { describe, expect, it, vi } from 'vitest'
import { AssignSupervisorDialog } from './AssignSupervisorDialog'
import type { User } from '../hooks/useTimesheetProvisioning'

const targetUser: User = {
  id: 2,
  name: 'Jane Doe',
  email: 'jane@example.com',
  qbo_employee_ref: '7',
  supervisor_id: null,
}

const supervisor: User = {
  id: 3,
  name: 'Bob Supervisor',
  email: 'bob@example.com',
  qbo_employee_ref: '8',
}

describe('AssignSupervisorDialog', () => {
  it('saves the selected supervisor', async () => {
    const user = userEvent.setup()
    const onSave = vi.fn().mockResolvedValue(undefined)
    const onClose = vi.fn()

    render(
      <LocaleProvider>
        <AssignSupervisorDialog
          user={targetUser}
          supervisorOptions={[targetUser, supervisor]}
          saving={false}
          onClose={onClose}
          onSave={onSave}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: 'Supervisor' }))
    await user.click(screen.getByRole('option', { name: 'Bob Supervisor' }))
    await user.click(screen.getByRole('button', { name: /^save$/i }))

    expect(onSave).toHaveBeenCalledWith(2, 3)
    expect(onClose).toHaveBeenCalled()
  })
})
