/**
 * @file Tests for draft time entry edit form state.
 */

import { LocaleProvider } from '@ellr/ui'
import { act, renderHook, waitFor } from '@testing-library/react'
import {
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  type TimeActivityRow,
} from '@ellr/api-client'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDraftTimeEntryEditForm } from './useDraftTimeEntryEditForm'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
    fetchQboServices: vi.fn(),
  }
})

function wrapper({ children }: { children: ReactNode }) {
  return <LocaleProvider>{children}</LocaleProvider>
}

const entry: TimeActivityRow = {
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
  isBillable: true,
  billableLocked: false,
  approvalStatus: 'draft',
}

describe('useDraftTimeEntryEditForm', () => {
  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboServices).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Design' }])
  })

  it('hydrates fields from the entry and saves updates', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined)
    const onClose = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryEditForm({ entry, saving: false, onClose, onSave }),
      { wrapper },
    )

    expect(result.current.open).toBe(true)
    expect(result.current.customer?.id).toBe('11')
    expect(result.current.project?.id).toBe('22')
    expect(result.current.service?.id).toBe('33')
    expect(result.current.description).toBe('Notes')
    expect(result.current.isBillable).toBe(true)

    act(() => {
      result.current.setDescription('  ')
      result.current.setIsBillable(false)
    })

    await act(async () => {
      await result.current.handleSave()
    })

    await waitFor(() => {
      expect(onSave).toHaveBeenCalledWith(
        'local:12',
        expect.objectContaining({
          customer_ref: '11',
          project_ref: '22',
          item_ref: '33',
          description: null,
          is_billable: false,
        }),
      )
    })
  })

  it('loads picker options and skips save when times are invalid', async () => {
    const onSave = vi.fn()
    const { result } = renderHook(
      () =>
        useDraftTimeEntryEditForm({
          entry,
          saving: false,
          onClose: vi.fn(),
          onSave,
        }),
      { wrapper },
    )

    await act(async () => {
      await result.current.customersSelect.onOpen()
      await result.current.servicesSelect.onOpen()
    })

    expect(fetchQboCustomers).toHaveBeenCalled()
    expect(fetchQboServices).toHaveBeenCalled()

    act(() => {
      result.current.setCustomer({ id: '11', display_name: 'Acme' })
      result.current.setProject({ id: '22', display_name: 'Website' })
      result.current.setService({ id: '33', display_name: 'Design' })
      result.current.setStartValue('')
      result.current.setEndValue('')
    })

    await act(async () => {
      await result.current.projectsSelect.onOpen()
      await result.current.handleSave()
    })

    expect(fetchQboProjects).toHaveBeenCalled()
    expect(onSave).not.toHaveBeenCalled()
  })
})
