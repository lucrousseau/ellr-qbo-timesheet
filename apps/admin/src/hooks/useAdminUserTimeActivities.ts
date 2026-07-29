/**
 * @file Administrator time activity list and edit state for a provisioned user.
 */

import {
  listAdminUserTimeActivities,
  parseTimeActivityRow,
  updateAdminUserTimeActivity,
  type TimeActivityRow,
  type TimeActivityUpdatePayload,
} from '@ellr/api-client'
import { getApiErrorMessage, useLocale } from '@ellr/ui'
import { useCallback, useEffect, useRef, useState } from 'react'

const PAGE_SIZE = 10

type UseAdminUserTimeActivitiesOptions = {
  userId: number | null
  enabled: boolean
  onSuccess?: (message: string) => void
  onError?: (message: string) => void
}

/**
 * Loads and updates time activities for a provisioned timesheet user.
 * @param options Target user id, enable flag, and optional flash callbacks.
 * @returns Entries, pagination, and save handler for administrator edits.
 */
export function useAdminUserTimeActivities({
  userId,
  enabled,
  onSuccess,
  onError,
}: UseAdminUserTimeActivitiesOptions) {
  const { t, locale } = useLocale()
  const [entries, setEntries] = useState<TimeActivityRow[]>([])
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [savingId, setSavingId] = useState<string | null>(null)
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

  const saveEntry = useCallback(
    async (activityId: string, payload: TimeActivityUpdatePayload) => {
      if (userId === null) {
        return
      }

      setSavingId(activityId)

      try {
        const updated = await updateAdminUserTimeActivity(userId, activityId, payload)
        const row = parseTimeActivityRow(updated)

        setEntries((current) => current.map((entry) => (entry.id === activityId ? row : entry)))
        onSuccess?.(t('timeActivity.saved'))
      } catch (caught) {
        onError?.(getApiErrorMessage(caught, t('timeActivity.saveFailed'), locale))
      } finally {
        setSavingId(null)
      }
    },
    [locale, onError, onSuccess, t, userId],
  )

  return {
    entries,
    loading,
    loadingMore,
    hasMore,
    error,
    savingId,
    loadMore,
    saveEntry,
  }
}
