/**
 * @file Editor fields for a pending time entry awaiting approval.
 */

import type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'
import { useTimeEntryEditForm } from '../hooks/useTimeEntryEditForm'
import { useLocale } from '../i18n/LocaleProvider'
import { Button } from './Button'
import { TimeEntryEditFields } from './TimeEntryEditFields'

type TimeEntryApprovalEditorProps = {
  entry: TimeActivityRow
  saving: boolean
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
  onCancel: () => void
}

/**
 * Edit form for pending approval rows (pickers, times, description, billable).
 * @param props Entry, save handler, and busy state.
 * @returns Pending-entry edit form.
 */
export function TimeEntryApprovalEditor({
  entry,
  saving,
  onSave,
  onCancel,
}: TimeEntryApprovalEditorProps) {
  const { t } = useLocale()
  const form = useTimeEntryEditForm({ entry, saving, onSave })

  return (
    <div className="space-y-4">
      <TimeEntryEditFields form={form} />
      <div className="flex flex-wrap gap-2">
        <Button type="button" disabled={form.busy} onClick={() => void form.handleSave()}>
          {form.busy ? t('admin.savingPendingEntry') : t('admin.savePendingEntry')}
        </Button>
        <Button type="button" variant="secondary" disabled={form.busy} onClick={onCancel}>
          {t('common.cancel')}
        </Button>
      </div>
    </div>
  )
}
