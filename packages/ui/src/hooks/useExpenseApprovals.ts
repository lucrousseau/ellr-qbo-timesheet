/**
 * @file Pending expense approvals for administrators and supervisors.
 */

import {
  approveExpense,
  listPendingExpenseApprovals,
  rejectExpense,
  type Expense,
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

type UseExpenseApprovalsOptions = {
  enabled: boolean
  admin?: boolean
  messages?: Partial<ApprovalMessages>
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Loads pending expenses and exposes approve/reject handlers.
 * @param options Enable flag, admin route toggle, message overrides, and flash callbacks.
 * @returns Pending expenses, loading state, and review handlers.
 */
export function useExpenseApprovals({
  enabled,
  admin = true,
  messages,
  onSuccess,
  onError,
}: UseExpenseApprovalsOptions) {
  const { t, locale } = useLocale()
  const resolvedMessages: ApprovalMessages = {
    approvalFailed: messages?.approvalFailed ?? t('expense.approveFailed'),
    approvalSuccess: messages?.approvalSuccess ?? t('expense.approveSuccess'),
    approvalSuccessQueued: messages?.approvalSuccessQueued ?? t('expense.approveSuccessQueued'),
    rejectionSuccess: messages?.rejectionSuccess ?? t('expense.rejectSuccess'),
  }
  const [expenses, setExpenses] = useState<Expense[]>([])
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [reviewingId, setReviewingId] = useState<number | null>(null)
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
        const response = await listPendingExpenseApprovals(
          { max_results: PAGE_SIZE, start_position: startPosition },
          admin,
        )

        setExpenses((current) => (mode === 'append' ? [...current, ...response.data] : response.data))
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
      setExpenses([])
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
    async (id: number) => {
      setReviewingId(id)

      try {
        const approved = await approveExpense(id, admin)
        onSuccess(
          approved.qbo_id ? resolvedMessages.approvalSuccess : resolvedMessages.approvalSuccessQueued,
        )
        refresh()
      } catch (caught) {
        onError(getApiErrorMessage(caught, resolvedMessages.approvalFailed, locale))
      } finally {
        setReviewingId(null)
      }
    },
    [
      admin,
      locale,
      onError,
      onSuccess,
      refresh,
      resolvedMessages.approvalFailed,
      resolvedMessages.approvalSuccess,
      resolvedMessages.approvalSuccessQueued,
    ],
  )

  const rejectEntry = useCallback(
    async (id: number, reason?: string | null) => {
      setReviewingId(id)

      try {
        await rejectExpense(id, reason, admin)
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
    expenses,
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
