/**
 * @file Time activity entry form for the timesheet app.
 */

import type { FlashMessage } from '@ellr/ui'
import {
  Alert,
  Button,
  cardClass,
  TextAreaField,
  TextField,
  useLocale,
} from '@ellr/ui'

type TimeActivityForm = {
  start_time: string
  end_time: string
  description: string
}

type TimeEntryFormProps = {
  employeeLabel: string
  form: TimeActivityForm
  submitting: boolean
  message: FlashMessage | null
  onFormChange: (form: TimeActivityForm) => void
  onSubmit: (event: React.FormEvent) => void
}

/**
 * Form to record a QuickBooks time activity for the linked employee.
 * @param props Employee label, form state, and submit handler.
 * @returns Time entry form card.
 */
export function TimeEntryForm({
  employeeLabel,
  form,
  submitting,
  message,
  onFormChange,
  onSubmit,
}: TimeEntryFormProps) {
  const { t } = useLocale()

  return (
    <form className={`space-y-5 ${cardClass}`} onSubmit={onSubmit}>
      <p className="text-sm text-slate-600">
        {t('timesheet.qboEmployeeLabel', { label: employeeLabel })}
      </p>

      <TextField
        label={t('timesheet.start')}
        type="datetime-local"
        required
        value={form.start_time}
        onChange={(event) => onFormChange({ ...form, start_time: event.target.value })}
      />

      <TextField
        label={t('timesheet.end')}
        type="datetime-local"
        required
        value={form.end_time}
        onChange={(event) => onFormChange({ ...form, end_time: event.target.value })}
      />

      <TextAreaField
        label={t('timesheet.description')}
        rows={4}
        value={form.description}
        onChange={(event) => onFormChange({ ...form, description: event.target.value })}
      />

      <Button type="submit" disabled={submitting}>
        {submitting ? t('common.saving') : t('timesheet.saveTime')}
      </Button>

      {message && <Alert variant={message.type}>{message.text}</Alert>}
    </form>
  )
}
