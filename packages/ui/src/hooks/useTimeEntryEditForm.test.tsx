/**
 * @file Tests for shared time entry edit form helpers and hook.
 */

import { act, renderHook, waitFor } from '@testing-library/react'
import {
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  type TimeActivityRow,
} from '@ellr/api-client'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import {
  optionFromRef,
  splitCustomerProjectLabel,
  useTimeEntryEditForm,
} from './useTimeEntryEditForm'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
    fetchQboServices: vi.fn(),
  }
})

/**
 * Wraps hook renders with the shared locale provider.
 * @param props React children.
 * @returns Locale-aware test wrapper.
 */
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
  customerName: 'Acme / Website',
  projectRef: '22',
  itemRef: '33',
  serviceName: 'Design',
  description: 'Notes',
  isBillable: true,
  billableLocked: false,
  approvalStatus: 'draft',
}

describe('useTimeEntryEditForm helpers', () => {
  it('builds picker options and splits combined client labels', () => {
    expect(optionFromRef('11', 'Acme')).toEqual({ id: '11', display_name: 'Acme' })
    expect(optionFromRef('11', null)).toEqual({ id: '11', display_name: '11' })
    expect(splitCustomerProjectLabel('Acme / Website')).toEqual({
      customerLabel: 'Acme',
      projectLabel: 'Website',
    })
  })
})

describe('useTimeEntryEditForm', () => {
  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboServices).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Design' }])
  })

  it('hydrates labels from the entry and saves updates', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined)
    const { result } = renderHook(
      () => useTimeEntryEditForm({ entry, saving: false, onSave }),
      { wrapper },
    )

    expect(result.current.customer).toEqual({ id: '11', display_name: 'Acme' })
    expect(result.current.project).toEqual({ id: '22', display_name: 'Website' })
    expect(result.current.service).toEqual({ id: '33', display_name: 'Design' })

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
})
