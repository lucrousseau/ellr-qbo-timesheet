/**
 * @file Tests for timesheet user provisioning hook.
 */

import { act, waitFor } from '@testing-library/react'
import { renderHook } from '@testing-library/react'
import { fetchTimesheetUsers, updateAdminUserSupervisor } from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTimesheetProvisioning } from './useTimesheetProvisioning'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboEmployees: vi.fn().mockResolvedValue([]),
    fetchTimesheetUsers: vi.fn(),
    updateAdminUserSupervisor: vi.fn(),
  }
})

function renderProvisioningHook() {
  const onError = vi.fn()
  const onSuccess = vi.fn()

  const view = renderHook(
    () =>
      useTimesheetProvisioning({
        status: { connected: true },
        isAdministrator: true,
        integrationsTabActive: true,
        onError,
        onSuccess,
      }),
    {
      wrapper: ({ children }) => <LocaleProvider>{children}</LocaleProvider>,
    },
  )

  return { ...view, onError, onSuccess }
}

describe('useTimesheetProvisioning', () => {
  beforeEach(() => {
    vi.mocked(fetchTimesheetUsers).mockResolvedValue([
      {
        id: 2,
        name: 'Jane Doe',
        email: 'jane@example.com',
        qbo_employee_ref: '7',
        qbo_employee_name: 'Jane Doe',
        supervisor_id: null,
      },
    ])
    vi.mocked(updateAdminUserSupervisor).mockResolvedValue({ supervisor_id: 3 })
  })

  it('assigns a supervisor and updates local user state', async () => {
    const { result, onSuccess } = renderProvisioningHook()

    await waitFor(() => {
      expect(result.current.users).toHaveLength(1)
    })

    await act(async () => {
      await result.current.assignSupervisor(2, 3)
    })

    expect(updateAdminUserSupervisor).toHaveBeenCalledWith(2, 3)
    expect(result.current.users[0]?.supervisor_id).toBe(3)
    expect(onSuccess).toHaveBeenCalled()
  })
})
