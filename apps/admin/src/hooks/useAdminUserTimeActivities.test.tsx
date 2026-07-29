/**
 * @file Tests for administrator time activity list and edit hook.
 */

import { act, renderHook, waitFor } from '@testing-library/react'
import {
  listAdminUserTimeActivities,
  updateAdminUserTimeActivity,
} from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useAdminUserTimeActivities } from './useAdminUserTimeActivities'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listAdminUserTimeActivities: vi.fn(),
    updateAdminUserTimeActivity: vi.fn(),
  }
})

const activity = {
  Id: '12',
  StartTime: '2026-07-29T09:00:00',
  EndTime: '2026-07-29T11:00:00',
  CustomerRef: { value: '1', name: 'Acme Corp' },
  ItemRef: { value: '2', name: 'Programming' },
  Description: 'Support work',
  BillableStatus: 'Billable',
}

function renderActivitiesHook(userId: number | null = 4, enabled = true) {
  const onSuccess = vi.fn()
  const onError = vi.fn()

  const view = renderHook(
    () =>
      useAdminUserTimeActivities({
        userId,
        enabled,
        onSuccess,
        onError,
      }),
    {
      wrapper: ({ children }) => <LocaleProvider>{children}</LocaleProvider>,
    },
  )

  return { ...view, onSuccess, onError }
}

describe('useAdminUserTimeActivities', () => {
  beforeEach(() => {
    vi.mocked(listAdminUserTimeActivities).mockResolvedValue({
      data: [activity],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: true },
    })
    vi.mocked(updateAdminUserTimeActivity).mockResolvedValue({
      ...activity,
      BillableStatus: 'NotBillable',
    })
  })

  it('loads entries when enabled for a user', async () => {
    const { result } = renderActivitiesHook()

    await waitFor(() => {
      expect(result.current.loading).toBe(false)
    })

    expect(listAdminUserTimeActivities).toHaveBeenCalledWith(4, {
      max_results: 10,
      start_position: 1,
    })
    expect(result.current.entries).toHaveLength(1)
    expect(result.current.entries[0]?.customerName).toBe('Acme Corp')
    expect(result.current.hasMore).toBe(true)
  })

  it('clears state when disabled', async () => {
    const { result, rerender } = renderHook(
      ({ enabled }) =>
        useAdminUserTimeActivities({
          userId: 4,
          enabled,
        }),
      {
        initialProps: { enabled: true },
        wrapper: ({ children }) => <LocaleProvider>{children}</LocaleProvider>,
      },
    )

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    rerender({ enabled: false })

    await waitFor(() => {
      expect(result.current.entries).toEqual([])
      expect(result.current.hasMore).toBe(false)
    })
  })

  it('loads more pages when requested', async () => {
    const { result } = renderActivitiesHook()

    await waitFor(() => {
      expect(result.current.hasMore).toBe(true)
    })

    vi.mocked(listAdminUserTimeActivities).mockResolvedValueOnce({
      data: [{ ...activity, Id: '13', Description: 'More work' }],
      meta: { count: 1, max_results: 10, start_position: 2, truncated: false },
    })

    await act(async () => {
      result.current.loadMore()
    })

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(2)
      expect(result.current.loadingMore).toBe(false)
    })

    expect(listAdminUserTimeActivities).toHaveBeenLastCalledWith(4, {
      max_results: 10,
      start_position: 2,
    })
  })

  it('saves an entry and notifies success', async () => {
    const { result, onSuccess } = renderActivitiesHook()

    await waitFor(() => {
      expect(result.current.entries).toHaveLength(1)
    })

    await act(async () => {
      await result.current.saveEntry('12', { is_billable: false })
    })

    expect(updateAdminUserTimeActivity).toHaveBeenCalledWith(4, '12', { is_billable: false })
    expect(result.current.entries[0]?.isBillable).toBe(false)
    expect(onSuccess).toHaveBeenCalledWith('Time entry updated.')
  })

  it('maps load failures to an error message', async () => {
    vi.mocked(listAdminUserTimeActivities).mockRejectedValue(new Error('network down'))

    const { result } = renderActivitiesHook()

    await waitFor(() => {
      expect(result.current.error).toBe('Unable to load time entries.')
    })

    expect(result.current.entries).toEqual([])
    expect(result.current.hasMore).toBe(false)
  })
})
