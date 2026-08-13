/**
 * @file Dialog to edit a draft time entry including client, project, and service.
 */

import {
  AppDialog,
  Button,
  TimeEntryEditFields,
  useLocale,
  useTimeEntryEditForm,
} from '@ellr/ui'
import type { TimeActivityRow, TimeEntryUpdatePayload } from '../hooks/useDraftTimeEntryEditForm'

type DraftTimeEntryEditDialogProps = {
  entry: TimeActivityRow | null
  saving: boolean
  onClose: () => void
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Modal editor for draft or rejected local time entries.
 * @param props Entry being edited and save handlers.
 * @returns Edit dialog or null when closed.
 */
export function DraftTimeEntryEditDialog({
  entry,
  saving,
  onClose,
  onSave,
}: DraftTimeEntryEditDialogProps) {
  const { t } = useLocale()
  const form = useTimeEntryEditForm({ entry, saving, onSave })

  return (
    <AppDialog
      open={form.open}
      title={t('timesheet.editDraftTitle')}
      onClose={onClose}
      panelClassName="w-full max-w-xl rounded-xl border border-brand-border bg-white p-6 shadow-xl"
    >
      <div className="space-y-4">
        <TimeEntryEditFields form={form} />
        <div className="flex justify-end gap-3">
          <Button type="button" variant="secondary" disabled={form.busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={form.busy} onClick={() => void form.handleSave()}>
            {form.busy ? t('timeActivity.saving') : t('timeActivity.save')}
          </Button>
        </div>
      </div>
    </AppDialog>
  )
}
