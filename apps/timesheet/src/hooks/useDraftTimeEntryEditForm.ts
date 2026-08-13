/**
 * @file Compatibility wrapper around the shared time entry edit form hook.
 */

import { useTimeEntryEditForm } from '@ellr/ui'
import type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'

export type { QboPickerOption, TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'

type UseDraftTimeEntryEditFormOptions = {
  entry: TimeActivityRow | null
  saving: boolean
  onClose: () => void
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Draft edit form state for timesheet tests and legacy callers.
 * @param options Entry being edited and save handlers.
 * @returns Shared edit-form state plus the dialog close handler.
 */
export function useDraftTimeEntryEditForm({
  entry,
  saving,
  onClose,
  onSave,
}: UseDraftTimeEntryEditFormOptions) {
  const form = useTimeEntryEditForm({ entry, saving, onSave })

  return {
    ...form,
    onClose,
  }
}
