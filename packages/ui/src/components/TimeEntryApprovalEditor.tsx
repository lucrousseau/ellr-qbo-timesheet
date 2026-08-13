/**
 * @file Inline editor fields for a pending time entry awaiting approval.
 */

import {
  fromDateTimeLocalValue,
  toDateTimeLocalValue,
  type TimeActivityRow,
  type TimeEntryUpdatePayload,
} from '@ellr/api-client'
import { useEffect, useState } from 'react'
import { Button } from './Button'
import { CheckboxField } from './CheckboxField'
import { TextAreaField } from './TextAreaField'
import { TextField } from './TextField'
import { useLocale } from '../i18n/LocaleProvider'
import { useGuardedAction } from '../hooks/useGuardedAction'

type TimeEntryApprovalEditorProps = {
  entry: TimeActivityRow
  saving: boolean
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
  onCancel: () => void
}

/**
 * Edit form for pending approval cards (times, refs, description, billable).
 * @param props Entry, save handler, and busy state.
 * @returns Inline edit form.
 */
export function TimeEntryApprovalEditor({
  entry,
  saving,
  onSave,
  onCancel,
}: TimeEntryApprovalEditorProps) {
  const { t } = useLocale()
  const [startValue, setStartValue] = useState(toDateTimeLocalValue(entry.startTime))
  const [endValue, setEndValue] = useState(toDateTimeLocalValue(entry.endTime))
  const [customerRef, setCustomerRef] = useState(entry.customerRef ?? '')
  const [projectRef, setProjectRef] = useState(entry.projectRef ?? '')
  const [itemRef, setItemRef] = useState(entry.itemRef ?? '')
  const [description, setDescription] = useState(entry.description ?? '')
  const [isBillable, setIsBillable] = useState(entry.isBillable)

  useEffect(() => {
    setStartValue(toDateTimeLocalValue(entry.startTime))
    setEndValue(toDateTimeLocalValue(entry.endTime))
    setCustomerRef(entry.customerRef ?? '')
    setProjectRef(entry.projectRef ?? '')
    setItemRef(entry.itemRef ?? '')
    setDescription(entry.description ?? '')
    setIsBillable(entry.isBillable)
  }, [entry])

  const { run: handleSave, pending } = useGuardedAction(async () => {
    const startTime = fromDateTimeLocalValue(startValue)
    const endTime = fromDateTimeLocalValue(endValue)

    if (!startTime || !endTime) {
      return
    }

    await onSave(entry.id, {
      start_time: startTime,
      end_time: endTime,
      customer_ref: customerRef.trim() === '' ? null : customerRef.trim(),
      project_ref: projectRef.trim() === '' ? null : projectRef.trim(),
      item_ref: itemRef.trim() === '' ? null : itemRef.trim(),
      description: description.trim() === '' ? null : description,
      is_billable: isBillable,
    })
  })

  const busy = saving || pending

  return (
    <div className="mt-4 space-y-3 rounded-md border border-brand-border bg-brand-surface-subtle p-3">
      <div className="grid gap-3 md:grid-cols-2">
        <TextField
          label={t('timeActivity.start')}
          type="datetime-local"
          value={startValue}
          disabled={busy}
          onChange={(event) => setStartValue(event.target.value)}
        />
        <TextField
          label={t('timeActivity.end')}
          type="datetime-local"
          value={endValue}
          disabled={busy}
          onChange={(event) => setEndValue(event.target.value)}
        />
        <TextField
          label={t('timesheet.client')}
          value={customerRef}
          disabled={busy}
          onChange={(event) => setCustomerRef(event.target.value)}
        />
        <TextField
          label={t('timesheet.project')}
          value={projectRef}
          disabled={busy}
          onChange={(event) => setProjectRef(event.target.value)}
        />
        <TextField
          label={t('timesheet.service')}
          value={itemRef}
          disabled={busy}
          onChange={(event) => setItemRef(event.target.value)}
        />
      </div>
      <TextAreaField
        label={t('timeActivity.description')}
        value={description}
        disabled={busy}
        onChange={(event) => setDescription(event.target.value)}
      />
      <CheckboxField
        label={t('timeActivity.billable')}
        checked={isBillable}
        disabled={busy}
        onChange={setIsBillable}
      />
      <div className="flex flex-wrap gap-2">
        <Button type="button" disabled={busy} onClick={() => void handleSave()}>
          {busy ? t('admin.savingPendingEntry') : t('admin.savePendingEntry')}
        </Button>
        <Button type="button" variant="secondary" disabled={busy} onClick={onCancel}>
          {t('common.cancel')}
        </Button>
      </div>
    </div>
  )
}
