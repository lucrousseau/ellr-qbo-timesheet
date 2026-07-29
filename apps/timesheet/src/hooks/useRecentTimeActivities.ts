/**
 * @file Paginated recent time activities for the signed-in timesheet user.
 */

import { listTimeActivities, parseTimeActivityRow, type TimeActivityRow } from '@ellr/api-client'
import { getApiErrorMessage, useLocale } from '@ellr/ui'
import { useCallback, useEffect, useRef, useState } from 'react'

const PAGE_SIZE = 10
const BACKGROUND_REFRESH_INTERVAL_MS = 15_000

type LoadMode = 'initial' | 'append' | 'background'

type UseRecentTimeActivitiesOptions = {
  enabled: boolean
  refreshToken: number
}

/**
 * Loads the most recent time activities with load-more pagination.
 * @param options Enable flag and refresh token after new entries are logged.
 * @returns Entries, loading state, and pagination handlers.
 */
export function useRecentTimeActivities({ enabled, refreshToken }: UseRecentTimeActivitiesOptions) {
  const { t, locale } = useLocale()
  const [entries, setEntries] = useState<TimeActivityRow[]>([])
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const nextStartPosition = useRef(1)
  const entriesLengthRef = useRef(0)
  const backgroundInFlightRef = useRef(false)
  const loadingRef = useRef(false)
  const loadingMoreRef = useRef(false)
  entriesLengthRef.current = entries.length
  loadingRef.current = loading
  loadingMoreRef.current = loadingMore

  const loadPage = useCallback(
    async (mode: LoadMode) => {
      if (!enabled) {
        return
      }

      if (mode === 'background') {
        if (backgroundInFlightRef.current || loadingRef.current || loadingMoreRef.current) {
          return
        }

        backgroundInFlightRef.current = true
      } else if (mode === 'append') {
        setLoadingMore(true)
      } else {
        setLoading(true)
        setError(null)
      }

      try {
        const startPosition = mode === 'append' ? nextStartPosition.current : 1
        const maxResults =
          mode === 'append' ? PAGE_SIZE : Math.max(PAGE_SIZE, entriesLengthRef.current)
        const response = await listTimeActivities({
          max_results: maxResults,
          start_position: startPosition,
        })
        const rows = response.data.map(parseTimeActivityRow)

        setEntries((current) => (mode === 'append' ? [...current, ...rows] : rows))
        setHasMore(response.meta.truncated)

        if (mode === 'append') {
          nextStartPosition.current = startPosition + response.meta.count
        } else {
          nextStartPosition.current = response.meta.count + 1
        }
      } catch (caught) {
        if (mode !== 'background') {
          setError(getApiErrorMessage(caught, t('timeActivity.loadFailed'), locale))
        }

        if (mode === 'initial') {
          setHasMore(false)
        }
      } finally {
        if (mode === 'background') {
          backgroundInFlightRef.current = false
        } else if (mode === 'append') {
          setLoadingMore(false)
        } else {
          setLoading(false)
        }
      }
    },
    [enabled, locale, t],
  )

  useEffect(() => {
    if (!enabled) {
      setEntries([])
      setHasMore(false)
      setError(null)
      nextStartPosition.current = 1
      return
    }

    nextStartPosition.current = 1
    void loadPage('initial')
  }, [enabled, refreshToken, loadPage])

  useEffect(() => {
    if (!enabled) {
      return undefined
    }

    const refreshInBackground = () => {
      if (document.visibilityState !== 'visible') {
        return
      }

      void loadPage('background')
    }

    const intervalId = window.setInterval(refreshInBackground, BACKGROUND_REFRESH_INTERVAL_MS)

    const onVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void loadPage('background')
      }
    }

    document.addEventListener('visibilitychange', onVisibilityChange)

    return () => {
      window.clearInterval(intervalId)
      document.removeEventListener('visibilitychange', onVisibilityChange)
    }
  }, [enabled, loadPage])

  const loadMore = useCallback(() => {
    if (!hasMore || loadingMore || loading) {
      return
    }

    void loadPage('append')
  }, [hasMore, loadPage, loading, loadingMore])

  return {
    entries,
    loading,
    loadingMore,
    hasMore,
    error,
    loadMore,
  }
}
