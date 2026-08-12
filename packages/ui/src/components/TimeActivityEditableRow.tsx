/**
 * @file Editable row for administrator time activity tables.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { fromDateTimeLocalValue, toDateTimeLocalValue } from '@ellr/api-client'
import { useEffect, useState } from 'react'
import { Button } from './Button'
import { CheckboxField } from './CheckboxField'
import { TextAreaField } from './TextAreaField'
import { TextField } from './TextField'
import { useLocale } from '../i18n/LocaleProvider'
import { formatEntryDuration } from './timeActivityDisplay'

type TimeActivityEditableRowProps = {
  entry: TimeActivityRow
  saving: boolean
  onSave: (id: string, payload: TimeActivityUpdatePayload) => Promise<void>
}

/**
 * Inline-editable time activity row for administrator views.
 * @param props Entry data, save handler, and saving state.
 * @returns Table row with editable fields.
 */
export function TimeActivityEditableRow({ entry, saving, onSave }: TimeActivityEditableRowProps) {
  const { t } = useLocale()
  const [startValue, setStartValue] = useState(toDateTimeLocalValue(entry.startTime))
  const [endValue, setEndValue] = useState(toDateTimeLocalValue(entry.endTime))
  const [description, setDescription] = useState(entry.description ?? '')
  const [isBillable, setIsBillable] = useState(entry.isBillable)

  useEffect(() => {
    setStartValue(toDateTimeLocalValue(entry.startTime))
    setEndValue(toDateTimeLocalValue(entry.endTime))
    setDescription(entry.description ?? '')
    setIsBillable(entry.isBillable)
  }, [entry])

  const isDirty =
    startValue !== toDateTimeLocalValue(entry.startTime) ||
    endValue !== toDateTimeLocalValue(entry.endTime) ||
    description !== (entry.description ?? '') ||
    isBillable !== entry.isBillable

  const onSubmit = async () => {
    const startTime = fromDateTimeLocalValue(startValue)
    const endTime = fromDateTimeLocalValue(endValue)

    if (!startTime || !endTime) {
      return
    }

    await onSave(entry.id, {
      start_time: startTime,
      end_time: endTime,
      description: description.trim() === '' ? null : description,
      is_billable: isBillable,
    })
  }

  return (
    <tr className="align-top">
      <td className="px-3 py-3 text-sm text-brand-primary">
        <TextField
          label={t('timeActivity.start')}
          type="datetime-local"
          value={startValue}
          disabled={saving}
          onChange={(event) => setStartValue(event.target.value)}
        />
      </td>
      <td className="px-3 py-3 text-sm text-brand-primary">
        <TextField
          label={t('timeActivity.end')}
          type="datetime-local"
          value={endValue}
          disabled={saving}
          onChange={(event) => setEndValue(event.target.value)}
        />
      </td>
      <td className="px-3 py-3 text-sm text-brand-primary">{entry.customerName ?? t('timeActivity.noValue')}</td>
      <td className="px-3 py-3 text-sm text-brand-primary">{entry.serviceName ?? t('timeActivity.noValue')}</td>
      <td className="px-3 py-3 text-sm text-brand-primary">
        <TextAreaField
          label={t('timeActivity.description')}
          value={description}
          disabled={saving}
          onChange={(event) => setDescription(event.target.value)}
          rows={2}
        />
      </td>
      <td className="px-3 py-3 text-sm text-brand-primary">{formatEntryDuration(entry.durationSeconds)}</td>
      <td className="px-3 py-3 text-sm text-brand-primary">
        <CheckboxField
          label={t('timeActivity.billable')}
          checked={isBillable}
          disabled={saving || entry.billableLocked}
          onChange={setIsBillable}
        />
        {entry.billableLocked ? (
          <p className="mt-1 text-xs text-brand-muted-subtle">{t('timeActivity.billedLocked')}</p>
        ) : null}
      </td>
      <td className="px-3 py-3 text-sm text-brand-primary">
        <Button type="button" size="compact" disabled={saving || !isDirty} onClick={() => void onSubmit()}>
          {saving ? t('timeActivity.saving') : t('timeActivity.save')}
        </Button>
      </td>
    </tr>
  )
}
