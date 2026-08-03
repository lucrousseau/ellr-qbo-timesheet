/**
 * @file List, create, and delete local expenses for the signed-in user.
 */

import {
  createExpense,
  deleteExpense,
  listExpenses,
  type Expense,
  type ExpensePayload,
} from '@ellr/api-client'
import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from './useGuardedAction'

const PAGE_SIZE = 20

type UseExpensesOptions = {
  enabled: boolean
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Loads the signed-in user's expenses and exposes create/delete handlers.
 * @param options Enable flag and flash callbacks.
 * @returns Expense rows, loading state, and mutation handlers.
 */
export function useExpenses({ enabled, onSuccess, onError }: UseExpensesOptions) {
  const { t, locale } = useLocale()
  const [expenses, setExpenses] = useState<Expense[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const onSuccessRef = useRef(onSuccess)
  const onErrorRef = useRef(onError)

  onSuccessRef.current = onSuccess
  onErrorRef.current = onError

  const refresh = useCallback(async () => {
    if (!enabled) {
      return
    }

    setLoading(true)
    setError(null)

    try {
      const response = await listExpenses({ max_results: PAGE_SIZE, start_position: 1 })
      setExpenses(response.data)
    } catch (caught) {
      setError(getApiErrorMessage(caught, t('expense.loadFailed'), locale))
    } finally {
      setLoading(false)
    }
  }, [enabled, locale, t])

  useEffect(() => {
    if (!enabled) {
      setExpenses([])
      setError(null)
      return
    }

    void refresh()
  }, [enabled, refresh])

  const { run: submitExpense, pending: creating } = useGuardedAction(async (payload: ExpensePayload) => {
    try {
      await createExpense(payload)
      onSuccessRef.current(t('expense.created'))
      await refresh()
    } catch (caught) {
      onErrorRef.current(getApiErrorMessage(caught, t('expense.createFailed'), locale))
      throw caught
    }
  })

  const removeExpense = useCallback(
    async (id: number) => {
      setDeletingId(id)

      try {
        await deleteExpense(id)
        onSuccessRef.current(t('expense.deleted'))
        await refresh()
      } catch (caught) {
        onErrorRef.current(getApiErrorMessage(caught, t('expense.deleteFailed'), locale))
      } finally {
        setDeletingId(null)
      }
    },
    [locale, refresh, t],
  )

  return {
    expenses,
    loading,
    error,
    creating,
    deletingId,
    refresh,
    createExpense: submitExpense,
    deleteExpense: removeExpense,
  }
}
