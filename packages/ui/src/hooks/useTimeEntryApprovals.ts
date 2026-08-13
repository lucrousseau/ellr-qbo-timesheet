/**
 * @file Pending time entry approvals for administrators and supervisors.
 */

import {
  approveTimeEntry,
  listPendingTimeEntryApprovals,
  parseTimeEntryRow,
  rejectTimeEntry,
  resolveTimeEntryId,
  type TimeActivityRow,
} from '@ellr/api-client'
import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'

const PAGE_SIZE = 10

type ApprovalMessages = {
  approvalFailed: string
  approvalSuccess: string
  approvalSuccessQueued: string
  rejectionSuccess: string
}

type UseTimeEntryApprovalsOptions = {
  enabled: boolean
  admin?: boolean
  messages?: Partial<ApprovalMessages>
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Loads pending time entries and exposes approve/reject handlers.
 * @param options Enable flag, admin route toggle, message keys, and flash callbacks.
 * @returns Pending entries, loading state, and review handlers.
 */
export function useTimeEntryApprovals({
  enabled,
  admin = true,
  messages,
  onSuccess,
  onError,
}: UseTimeEntryApprovalsOptions) {
  const { t, locale } = useLocale()
  const resolvedMessages: ApprovalMessages = {
    approvalFailed: messages?.approvalFailed ?? t('admin.approvalFailed'),
    approvalSuccess: messages?.approvalSuccess ?? t('admin.approvalSuccess'),
    approvalSuccessQueued: messages?.approvalSuccessQueued ?? t('admin.approvalSuccessQueued'),
    rejectionSuccess: messages?.rejectionSuccess ?? t('admin.rejectionSuccess'),
  }
  const [entries, setEntries] = useState<TimeActivityRow[]>([])
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [reviewingId, setReviewingId] = useState<string | null>(null)
  const nextStartPosition = useRef(1)

  const loadPage = useCallback(
    async (mode: 'initial' | 'append') => {
      if (!enabled) {
        return
      }

      if (mode === 'append') {
        setLoadingMore(true)
      } else {
        setLoading(true)
        setError(null)
      }

      try {
        const startPosition = mode === 'append' ? nextStartPosition.current : 1
        const response = await listPendingTimeEntryApprovals(
          { max_results: PAGE_SIZE, start_position: startPosition },
          { admin },
        )
        const rows = response.data.map(parseTimeEntryRow)

        setEntries((current) => (mode === 'append' ? [...current, ...rows] : rows))
        setHasMore(response.meta.truncated)
        nextStartPosition.current =
          mode === 'append' ? startPosition + response.meta.count : response.meta.count + 1
      } catch (caught) {
        setError(getApiErrorMessage(caught, resolvedMessages.approvalFailed, locale))
      } finally {
        if (mode === 'append') {
          setLoadingMore(false)
        } else {
          setLoading(false)
        }
      }
    },
    [admin, enabled, locale, resolvedMessages.approvalFailed],
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
  }, [enabled, loadPage])

  const refresh = useCallback(() => {
    nextStartPosition.current = 1
    void loadPage('initial')
  }, [loadPage])

  const approveEntry = useCallback(
    async (id: string, options: { groupForQbo?: boolean } = {}) => {
      setReviewingId(id)

      try {
        const approved = await approveTimeEntry(resolveTimeEntryId(id), {
          admin,
          groupForQbo: options.groupForQbo,
        })
        onSuccess(
          approved.qbo_id
            ? resolvedMessages.approvalSuccess
            : options.groupForQbo
              ? resolvedMessages.approvalSuccessQueued
              : resolvedMessages.approvalSuccess,
        )
        refresh()
      } catch (caught) {
        onError(getApiErrorMessage(caught, resolvedMessages.approvalFailed, locale))
      } finally {
        setReviewingId(null)
      }
    },
    [admin, locale, onError, onSuccess, refresh, resolvedMessages.approvalFailed, resolvedMessages.approvalSuccess, resolvedMessages.approvalSuccessQueued],
  )

  const rejectEntry = useCallback(
    async (id: string, reason?: string | null) => {
      setReviewingId(id)

      try {
        await rejectTimeEntry(resolveTimeEntryId(id), reason, { admin })
        onSuccess(resolvedMessages.rejectionSuccess)
        refresh()
      } catch (caught) {
        onError(getApiErrorMessage(caught, resolvedMessages.approvalFailed, locale))
      } finally {
        setReviewingId(null)
      }
    },
    [admin, locale, onError, onSuccess, refresh, resolvedMessages.approvalFailed, resolvedMessages.rejectionSuccess],
  )

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
    reviewingId,
    loadMore,
    approveEntry,
    rejectEntry,
  }
}
