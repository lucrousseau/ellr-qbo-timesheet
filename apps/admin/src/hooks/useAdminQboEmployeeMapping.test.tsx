/**
 * @file Tests for admin QuickBooks employee self-mapping hook.
 */

import { act, waitFor } from '@testing-library/react'
import { renderHook } from '@testing-library/react'
import { fetchQboEmployees, updateQboEmployee, type User } from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdminQboEmployeeMapping } from './useAdminQboEmployeeMapping'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboEmployees: vi.fn().mockResolvedValue([]),
    updateQboEmployee: vi.fn(),
  }
})

const adminUser: User = {
  id: 1,
  first_name: 'Luc',
  name: 'Luc Rousseau',
  email: 'luc@ellr.ca',
  is_admin: true,
  qbo_employee_ref: null,
}

function renderMappingHook(user: User = adminUser) {
  const onError = vi.fn()
  const onSuccess = vi.fn()
  const setUser = vi.fn()

  const view = renderHook(
    () =>
      useAdminQboEmployeeMapping({
        status: { connected: true },
        isAdministrator: true,
        integrationsTabActive: true,
        user,
        setUser,
        occupiedEmployeeRefs: new Set(['99']),
        onError,
        onSuccess,
      }),
    {
      wrapper: ({ children }) => <LocaleProvider>{children}</LocaleProvider>,
    },
  )

  return { ...view, onError, onSuccess, setUser }
}

describe('useAdminQboEmployeeMapping', () => {
  beforeEach(() => {
    vi.mocked(fetchQboEmployees).mockResolvedValue([
      { id: '42', display_name: 'Luc Rousseau', email: 'luc@ellr.ca' },
      { id: '99', display_name: 'Taken Employee', email: 'taken@example.com' },
    ])
    vi.mocked(updateQboEmployee).mockReset()
  })

  it('links the selected employee to the admin account', async () => {
    vi.mocked(updateQboEmployee).mockResolvedValue({
      ...adminUser,
      qbo_employee_ref: '42',
    })

    const { result, onSuccess, onError, setUser } = renderMappingHook()

    await act(async () => {
      await result.current.onEmployeeDropdownOpen()
    })

    await waitFor(() => {
      expect(result.current.employees).toEqual([
        { id: '42', display_name: 'Luc Rousseau', email: 'luc@ellr.ca' },
      ])
    })

    act(() => {
      result.current.onEmployeeChange({
        id: '42',
        display_name: 'Luc Rousseau',
        email: 'luc@ellr.ca',
      })
    })

    await act(async () => {
      await result.current.onSaveMapping({
        preventDefault: () => undefined,
      } as React.FormEvent)
    })

    await waitFor(() => {
      expect(updateQboEmployee).toHaveBeenCalledWith('42')
      expect(setUser).toHaveBeenCalledWith(
        expect.objectContaining({ qbo_employee_ref: '42' }),
      )
      expect(onSuccess).toHaveBeenCalled()
      expect(onError).not.toHaveBeenCalled()
    })
  })

  it('does not save when the selection matches the current mapping', async () => {
    const { result } = renderMappingHook({
      ...adminUser,
      qbo_employee_ref: '42',
    })

    act(() => {
      result.current.onEmployeeChange({
        id: '42',
        display_name: 'Luc Rousseau',
        email: 'luc@ellr.ca',
      })
    })

    await act(async () => {
      await result.current.onSaveMapping({
        preventDefault: () => undefined,
      } as React.FormEvent)
    })

    expect(updateQboEmployee).not.toHaveBeenCalled()
  })
})
