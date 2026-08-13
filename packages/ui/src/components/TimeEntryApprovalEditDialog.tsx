/**
 * @file Dialog editor for a pending time entry awaiting approval.
 */

import type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'
import { AppDialog } from './AppDialog'
import { TimeEntryApprovalEditor } from './TimeEntryApprovalEditor'
import { useLocale } from '../i18n/LocaleProvider'

type TimeEntryApprovalEditDialogProps = {
  entry: TimeActivityRow | null
  saving: boolean
  onClose: () => void
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Modal wrapper around {@link TimeEntryApprovalEditor} for table review flows.
 * @param props Entry under edit, busy state, and save handlers.
 * @returns Dialog markup, or null when closed.
 */
export function TimeEntryApprovalEditDialog({
  entry,
  saving,
  onClose,
  onSave,
}: TimeEntryApprovalEditDialogProps) {
  const { t } = useLocale()

  return (
    <AppDialog
      open={entry !== null}
      title={t('admin.editPendingEntry')}
      onClose={onClose}
      panelClassName="w-full max-w-2xl rounded-xl border border-brand-border bg-white p-6 shadow-xl"
    >
      {entry ? (
        <TimeEntryApprovalEditor
          entry={entry}
          saving={saving}
          onCancel={onClose}
          onSave={async (id, payload) => {
            await onSave(id, payload)
            onClose()
          }}
        />
      ) : null}
    </AppDialog>
  )
}
