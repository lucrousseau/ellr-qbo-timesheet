/**
 * @file Tests for QuickBooks project creation hook.
 */

import { act, waitFor } from '@testing-library/react'
import { renderHook } from '@testing-library/react'
import { createQboProject, fetchAdminQboCustomers } from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCreateQboProject } from './useCreateQboProject'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchAdminQboCustomers: vi.fn().mockResolvedValue([]),
    createQboProject: vi.fn(),
  }
})

function renderProjectHook() {
  const onError = vi.fn()
  const onSuccess = vi.fn()

  const view = renderHook(
    () =>
      useCreateQboProject({
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

describe('useCreateQboProject', () => {
  beforeEach(() => {
    vi.mocked(fetchAdminQboCustomers).mockResolvedValue([
      { id: '11', display_name: 'Acme Corp' },
    ])
    vi.mocked(createQboProject).mockReset()
  })

  it('creates a project for the selected client', async () => {
    vi.mocked(createQboProject).mockResolvedValue({
      id: '55',
      display_name: 'Website redesign',
    })

    const { result, onSuccess, onError } = renderProjectHook()

    await act(async () => {
      await result.current.onCustomerDropdownOpen()
    })

    act(() => {
      result.current.onCustomerChange({ id: '11', display_name: 'Acme Corp' })
      result.current.onDisplayNameChange('Website redesign')
    })

    await act(async () => {
      await result.current.onCreateProject({
        preventDefault: () => undefined,
      } as React.FormEvent)
    })

    await waitFor(() => {
      expect(createQboProject).toHaveBeenCalledWith({
        customer_ref: '11',
        display_name: 'Website redesign',
      })
      expect(onSuccess).toHaveBeenCalled()
      expect(onError).not.toHaveBeenCalled()
      expect(result.current.displayName).toBe('')
    })
  })

  it('reports create failures', async () => {
    vi.mocked(createQboProject).mockRejectedValue(new Error('create failed'))

    const { result, onSuccess, onError } = renderProjectHook()

    act(() => {
      result.current.onCustomerChange({ id: '11', display_name: 'Acme Corp' })
      result.current.onDisplayNameChange('Website redesign')
    })

    await act(async () => {
      await result.current.onCreateProject({
        preventDefault: () => undefined,
      } as React.FormEvent)
    })

    await waitFor(() => {
      expect(onError).toHaveBeenCalled()
      expect(onSuccess).not.toHaveBeenCalled()
    })
  })

  it('skips create when the form is incomplete', async () => {
    const { result } = renderProjectHook()

    await act(async () => {
      await result.current.onCreateProject({
        preventDefault: () => undefined,
      } as React.FormEvent)
    })

    expect(createQboProject).not.toHaveBeenCalled()
  })
})
