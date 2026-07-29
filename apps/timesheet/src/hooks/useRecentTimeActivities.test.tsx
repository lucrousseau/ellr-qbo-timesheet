/**
 * @file Tests for recent time activity list loading and background refresh.
 */

import { LocaleProvider } from '@ellr/ui'
import { act, renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { listTimeActivities } from '@ellr/api-client'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useRecentTimeActivities } from './useRecentTimeActivities'

const BACKGROUND_REFRESH_INTERVAL_MS = 15_000

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    listTimeActivities: vi.fn(),
  }
})

const emptyListResponse = {
  data: [],
  meta: { count: 0, max_results: 10, start_position: 1, truncated: false },
}

function wrapper({ children }: { children: ReactNode }) {
  return <LocaleProvider>{children}</LocaleProvider>
}

describe('useRecentTimeActivities', () => {
  beforeEach(() => {
    vi.mocked(listTimeActivities).mockReset()
    vi.mocked(listTimeActivities).mockResolvedValue(emptyListResponse)
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('loads entries on mount', async () => {
    const { result } = renderHook(
      () => useRecentTimeActivities({ enabled: true, refreshToken: 0 }),
      { wrapper },
    )

    await waitFor(() => {
      expect(result.current.loading).toBe(false)
    })

    expect(listTimeActivities).toHaveBeenCalledWith({
      max_results: 10,
      start_position: 1,
    })
  })

  it('refreshes silently on an interval when the tab stays visible', async () => {
    vi.useFakeTimers()

    renderHook(() => useRecentTimeActivities({ enabled: true, refreshToken: 0 }), { wrapper })

    await act(async () => {
      await vi.runOnlyPendingTimersAsync()
    })

    expect(listTimeActivities).toHaveBeenCalledTimes(1)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(BACKGROUND_REFRESH_INTERVAL_MS)
    })

    expect(listTimeActivities).toHaveBeenCalledTimes(2)
  })

  it('does not show the loading state during background refresh', async () => {
    vi.useFakeTimers()
    let resolveList: (value: typeof emptyListResponse) => void = () => {}
    vi.mocked(listTimeActivities)
      .mockResolvedValueOnce(emptyListResponse)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveList = resolve
          }),
      )

    const { result } = renderHook(
      () => useRecentTimeActivities({ enabled: true, refreshToken: 0 }),
      { wrapper },
    )

    await act(async () => {
      await vi.runOnlyPendingTimersAsync()
    })

    expect(result.current.loading).toBe(false)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(BACKGROUND_REFRESH_INTERVAL_MS)
    })

    expect(result.current.loading).toBe(false)

    await act(async () => {
      resolveList(emptyListResponse)
      await vi.runOnlyPendingTimersAsync()
    })
  })

  it('refreshes when the tab becomes visible again', async () => {
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')

    renderHook(() => useRecentTimeActivities({ enabled: true, refreshToken: 0 }), { wrapper })

    await waitFor(() => {
      expect(listTimeActivities).toHaveBeenCalledTimes(1)
    })

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')

    await act(async () => {
      document.dispatchEvent(new Event('visibilitychange'))
    })

    await waitFor(() => {
      expect(listTimeActivities).toHaveBeenCalledTimes(2)
    })
  })
})
