/**
 * @file Tests for draft time entry mutation handlers.
 */

import { LocaleProvider } from '@ellr/ui'
import { act, renderHook, waitFor } from '@testing-library/react'
import {
  deleteTimeEntry,
  submitTimeEntries,
  submitTimeEntry,
  updateTimeEntry,
  type TimeActivityRow,
} from '@ellr/api-client'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDraftTimeEntryActions } from './useDraftTimeEntryActions'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    deleteTimeEntry: vi.fn(),
    submitTimeEntries: vi.fn(),
    submitTimeEntry: vi.fn(),
    updateTimeEntry: vi.fn(),
  }
})

function wrapper({ children }: { children: ReactNode }) {
  return <LocaleProvider>{children}</LocaleProvider>
}

const draftEntry: TimeActivityRow = {
  id: 'local:12',
  timeEntryId: 12,
  startTime: '2026-07-30T09:00:00Z',
  endTime: '2026-07-30T10:00:00Z',
  durationSeconds: 3600,
  customerName: 'Acme',
  serviceName: 'Design',
  description: null,
  isBillable: false,
  billableLocked: false,
  approvalStatus: 'draft',
}

describe('useDraftTimeEntryActions', () => {
  beforeEach(() => {
    vi.mocked(deleteTimeEntry).mockReset()
    vi.mocked(submitTimeEntries).mockReset()
    vi.mocked(submitTimeEntry).mockReset()
    vi.mocked(updateTimeEntry).mockReset()
    vi.mocked(submitTimeEntry).mockResolvedValue({
      id: 12,
      list_id: 'local:12',
      user_id: 1,
      start_time: '2026-07-30T09:00:00Z',
      end_time: '2026-07-30T10:00:00Z',
      duration_seconds: 3600,
      is_billable: false,
      status: 'pending',
    })
    vi.mocked(submitTimeEntries).mockResolvedValue([
      {
        id: 12,
        list_id: 'local:12',
        user_id: 1,
        start_time: '2026-07-30T09:00:00Z',
        end_time: '2026-07-30T10:00:00Z',
        duration_seconds: 3600,
        is_billable: false,
        status: 'pending',
      },
    ])
    vi.mocked(deleteTimeEntry).mockResolvedValue(undefined)
    vi.mocked(updateTimeEntry).mockResolvedValue({
      id: 12,
      list_id: 'local:12',
      user_id: 1,
      start_time: '2026-07-30T09:00:00Z',
      end_time: '2026-07-30T11:00:00Z',
      duration_seconds: 7200,
      is_billable: true,
      status: 'draft',
    })
  })

  it('submits one draft entry and refreshes', async () => {
    const onChanged = vi.fn()
    const onSuccess = vi.fn()
    const onError = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryActions({ onChanged, onSuccess, onError }),
      { wrapper },
    )

    await act(async () => {
      await result.current.submitEntry('local:12')
    })

    expect(submitTimeEntry).toHaveBeenCalledWith(12)
    expect(onSuccess).toHaveBeenCalled()
    expect(onChanged).toHaveBeenCalled()
  })

  it('submits all drafts', async () => {
    const onChanged = vi.fn()
    const onSuccess = vi.fn()
    const onError = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryActions({ onChanged, onSuccess, onError }),
      { wrapper },
    )

    await act(async () => {
      await result.current.submitAllDrafts()
    })

    expect(submitTimeEntries).toHaveBeenCalled()
    expect(onSuccess).toHaveBeenCalled()
    expect(onChanged).toHaveBeenCalled()
  })

  it('deletes a draft entry', async () => {
    const onChanged = vi.fn()
    const onSuccess = vi.fn()
    const onError = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryActions({ onChanged, onSuccess, onError }),
      { wrapper },
    )

    await act(async () => {
      await result.current.deleteEntry('local:12')
    })

    expect(deleteTimeEntry).toHaveBeenCalledWith(12)
    expect(onSuccess).toHaveBeenCalled()
    expect(onChanged).toHaveBeenCalled()
  })

  it('saves a draft entry and closes the editor', async () => {
    const onChanged = vi.fn()
    const onSuccess = vi.fn()
    const onError = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryActions({ onChanged, onSuccess, onError }),
      { wrapper },
    )

    act(() => {
      result.current.setEditingEntry(draftEntry)
    })

    await act(async () => {
      await result.current.saveEntry('local:12', {
        start_time: '2026-07-30T09:00:00Z',
        end_time: '2026-07-30T11:00:00Z',
        is_billable: true,
      })
    })

    expect(updateTimeEntry).toHaveBeenCalledWith(12, expect.objectContaining({ is_billable: true }))
    expect(result.current.editingEntry).toBeNull()
    expect(onSuccess).toHaveBeenCalled()
    expect(onChanged).toHaveBeenCalled()
  })

  it('reports submit failures', async () => {
    vi.mocked(submitTimeEntry).mockRejectedValue(new Error('offline'))
    const onChanged = vi.fn()
    const onSuccess = vi.fn()
    const onError = vi.fn()
    const { result } = renderHook(
      () => useDraftTimeEntryActions({ onChanged, onSuccess, onError }),
      { wrapper },
    )

    await act(async () => {
      await result.current.submitEntry('local:12')
    })

    await waitFor(() => {
      expect(onError).toHaveBeenCalled()
    })
    expect(onChanged).not.toHaveBeenCalled()
  })
})
