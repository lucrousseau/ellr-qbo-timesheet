/**
 * @file Tests for pending time entry approval hook.
 */

import { act, waitFor } from '@testing-library/react'
import {
  approveTimeEntry,
  listPendingTimeEntryApprovals,
  rejectTimeEntry,
  type TimeEntry,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useTimeEntryApprovals } from './useTimeEntryApprovals'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listPendingTimeEntryApprovals: vi.fn(),
    approveTimeEntry: vi.fn(),
    rejectTimeEntry: vi.fn(),
    updatePendingTimeEntryApproval: vi.fn(),
  }
})

const pendingEntry: TimeEntry = {
  id: 12,
  list_id: 'local:12',
  user_id: 2,
  employee_name: 'Bob LeMoche',
  start_time: '2026-07-30T17:00:00Z',
  end_time: '2026-07-30T18:00:00Z',
  duration_seconds: 3600,
  is_billable: false,
  status: 'pending',
}

/**
 * Renders the approvals hook with default success and error callbacks.
 * @param admin Whether to use the admin approval route.
 * @returns Hook render result and mocked callbacks.
 */
function renderApprovalsHook(admin = true) {
  const onSuccess = vi.fn()
  const onError = vi.fn()

  const view = renderHookWithLocale(() =>
    useTimeEntryApprovals({
      enabled: true,
      admin,
      onSuccess,
      onError,
    }),
  )

  return { ...view, onSuccess, onError }
}

describe('useTimeEntryApprovals', () => {
  beforeEach(() => {
    vi.mocked(listPendingTimeEntryApprovals).mockReset()
    vi.mocked(approveTimeEntry).mockReset()
    vi.mocked(rejectTimeEntry).mockReset()
    vi.mocked(listPendingTimeEntryApprovals).mockResolvedValue({
      data: [pendingEntry],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    })
    vi.mocked(approveTimeEntry).mockResolvedValue({ ...pendingEntry, status: 'approved' })
    vi.mocked(rejectTimeEntry).mockResolvedValue({ ...pendingEntry, status: 'rejected' })
  })

  it('loads pending entries when enabled', async () => {
    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    expect(listPendingTimeEntryApprovals).toHaveBeenCalledWith(
      { max_results: 10, start_position: 1 },
      { admin: true },
    )
    expect(result.current.entries[0]?.id).toBe('local:12')
  })

  it('shows a queued sync message when approval returns without a quickbooks id', async () => {
    vi.mocked(approveTimeEntry).mockResolvedValue({ ...pendingEntry, status: 'approved', qbo_id: null })

    const { result, onSuccess } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry('local:12')
    })

    expect(onSuccess).toHaveBeenCalledWith('Time entry approved. QuickBooks sync is in progress.')
  })

  it('approves an entry and refreshes the list', async () => {
    const { result, onSuccess } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry('local:12')
    })

    expect(approveTimeEntry).toHaveBeenCalledWith(12, { admin: true })
    expect(onSuccess).toHaveBeenCalled()
    expect(listPendingTimeEntryApprovals).toHaveBeenCalledTimes(2)
  })

  it('rejects an entry through the supervisor route', async () => {
    const { result, onSuccess } = renderApprovalsHook(false)

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    await act(async () => {
      await result.current.rejectEntry('local:12', 'Missing details')
    })

    expect(rejectTimeEntry).toHaveBeenCalledWith(12, 'Missing details', { admin: false })
    expect(onSuccess).toHaveBeenCalled()
  })

  it('reports api errors when loading fails', async () => {
    vi.mocked(listPendingTimeEntryApprovals).mockRejectedValue(new Error('network'))

    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.error).toBeTruthy()
    })
  })

  it('clears entries when disabled', async () => {
    const onSuccess = vi.fn()
    const onError = vi.fn()

    const { result, rerender } = renderHookWithLocale(() =>
      useTimeEntryApprovals({
        enabled: false,
        admin: true,
        onSuccess,
        onError,
      }),
    )

    expect(result.current.entries).toEqual([])
    expect(listPendingTimeEntryApprovals).not.toHaveBeenCalled()

    rerender()
  })

  it('loads more entries when additional pages are available', async () => {
    vi.mocked(listPendingTimeEntryApprovals)
      .mockResolvedValueOnce({
        data: [pendingEntry],
        meta: { count: 1, max_results: 10, start_position: 1, truncated: true },
      })
      .mockResolvedValueOnce({
        data: [{ ...pendingEntry, id: 13, list_id: 'local:13' }],
        meta: { count: 1, max_results: 10, start_position: 2, truncated: false },
      })

    const { result } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.hasMore).toBe(true)
    })

    act(() => {
      result.current.loadMore()
    })

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(2)
    })
  })

  it('surfaces approval errors through onError', async () => {
    vi.mocked(approveTimeEntry).mockRejectedValue(new Error('network'))

    const { result, onError } = renderApprovalsHook()

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    await act(async () => {
      await result.current.approveEntry('local:12')
    })

    expect(onError).toHaveBeenCalled()
  })
})
