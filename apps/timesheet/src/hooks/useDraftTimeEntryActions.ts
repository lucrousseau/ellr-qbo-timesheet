/**
 * @file Draft/rejected time entry mutations for the timesheet recent entries list.
 */

import {
  deleteTimeEntry,
  resolveTimeEntryId,
  submitTimeEntries,
  submitTimeEntry,
  updateTimeEntry,
  type TimeActivityRow,
  type TimeEntryUpdatePayload,
} from '@ellr/api-client'
import { getApiErrorMessage, useGuardedAction, useLocale } from '@ellr/ui'
import { useCallback, useState } from 'react'

export type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'

type UseDraftTimeEntryActionsOptions = {
  onChanged: () => void
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Submit, update, and delete handlers for employee draft time entries.
 * @param options Refresh and flash callbacks.
 * @returns Action handlers and busy state.
 */
export function useDraftTimeEntryActions({
  onChanged,
  onSuccess,
  onError,
}: UseDraftTimeEntryActionsOptions) {
  const { t, locale } = useLocale()
  const [actionEntryId, setActionEntryId] = useState<string | null>(null)
  const [editingEntry, setEditingEntry] = useState<TimeActivityRow | null>(null)

  const submitEntry = useCallback(
    async (id: string) => {
      setActionEntryId(id)

      try {
        await submitTimeEntry(resolveTimeEntryId(id))
        onSuccess(t('timesheet.submitSuccess'))
        onChanged()
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('timesheet.submitFailed'), locale))
      } finally {
        setActionEntryId(null)
      }
    },
    [locale, onChanged, onError, onSuccess, t],
  )

  const { run: submitSelectedDrafts, pending: submittingSelected } = useGuardedAction(
    async (ids: number[]) => {
      if (ids.length === 0) {
        return
      }

      try {
        await submitTimeEntries(ids)
        onSuccess(t('timesheet.submitSelectedSuccess'))
        onChanged()
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('timesheet.submitFailed'), locale))
      }
    },
  )

  const deleteEntry = useCallback(
    async (id: string) => {
      setActionEntryId(id)

      try {
        await deleteTimeEntry(resolveTimeEntryId(id))
        onSuccess(t('timesheet.deleteDraftSuccess'))
        onChanged()
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('timeActivity.saveFailed'), locale))
      } finally {
        setActionEntryId(null)
      }
    },
    [locale, onChanged, onError, onSuccess, t],
  )

  const saveEntry = useCallback(
    async (id: string, payload: TimeEntryUpdatePayload) => {
      setActionEntryId(id)

      try {
        await updateTimeEntry(resolveTimeEntryId(id), payload)
        onSuccess(t('timesheet.draftSaved'))
        setEditingEntry(null)
        onChanged()
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('timeActivity.saveFailed'), locale))
        throw caught
      } finally {
        setActionEntryId(null)
      }
    },
    [locale, onChanged, onError, onSuccess, t],
  )

  return {
    actionEntryId,
    editingEntry,
    setEditingEntry,
    submitEntry,
    submitSelectedDrafts,
    submittingSelected,
    deleteEntry,
    saveEntry,
  }
}
