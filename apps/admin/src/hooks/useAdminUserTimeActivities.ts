/**
 * @file Administrator time activity list state for a provisioned user.
 */

import {
  listAdminUserTimeActivities,
  parseTimeActivityRow,
  type TimeActivityRow,
} from '@ellr/api-client'
import { getApiErrorMessage, useLocale } from '@ellr/ui'
import { useCallback, useEffect, useRef, useState } from 'react'

const PAGE_SIZE = 10

type UseAdminUserTimeActivitiesOptions = {
  userId: number | null
  enabled: boolean
}

/**
 * Loads QuickBooks-synced time activities for a provisioned timesheet user.
 * @param options Target user id and enable flag.
 * @returns Entries and pagination handlers (read-only after approval).
 */
export function useAdminUserTimeActivities({
  userId,
  enabled,
}: UseAdminUserTimeActivitiesOptions) {
  const { t, locale } = useLocale()
  const [entries, setEntries] = useState<TimeActivityRow[]>([])
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const nextStartPosition = useRef(1)

  const loadPage = useCallback(
    async (append: boolean) => {
      if (!enabled || userId === null) {
        return
      }

      if (append) {
        setLoadingMore(true)
      } else {
        setLoading(true)
        setError(null)
      }

      try {
        const startPosition = append ? nextStartPosition.current : 1
        const response = await listAdminUserTimeActivities(userId, {
          max_results: PAGE_SIZE,
          start_position: startPosition,
        })
        const rows = response.data.map(parseTimeActivityRow)

        setEntries((current) => (append ? [...current, ...rows] : rows))
        setHasMore(response.meta.truncated)
        nextStartPosition.current = startPosition + response.meta.count
      } catch (caught) {
        setError(getApiErrorMessage(caught, t('timeActivity.loadFailed'), locale))
        if (!append) {
          setEntries([])
          setHasMore(false)
        }
      } finally {
        setLoading(false)
        setLoadingMore(false)
      }
    },
    [enabled, locale, t, userId],
  )

  useEffect(() => {
    if (!enabled || userId === null) {
      setEntries([])
      setHasMore(false)
      setError(null)
      nextStartPosition.current = 1
      return
    }

    nextStartPosition.current = 1
    void loadPage(false)
  }, [enabled, userId, loadPage])

  const loadMore = useCallback(() => {
    if (!hasMore || loadingMore || loading) {
      return
    }

    void loadPage(true)
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
