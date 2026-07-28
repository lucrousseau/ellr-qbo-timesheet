/**
 * @file Time activity entry form for the timesheet app.
 */

import type { FlashMessage } from '@ellr/ui'
import { Alert, cardClass, inputClass, primaryButtonClass, useLocale } from '@ellr/ui'

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

      <label className="block text-sm font-medium text-slate-700">
        {t('timesheet.start')}
        <input
          type="datetime-local"
          required
          className={inputClass}
          value={form.start_time}
          onChange={(event) => onFormChange({ ...form, start_time: event.target.value })}
        />
      </label>

      <label className="block text-sm font-medium text-slate-700">
        {t('timesheet.end')}
        <input
          type="datetime-local"
          required
          className={inputClass}
          value={form.end_time}
          onChange={(event) => onFormChange({ ...form, end_time: event.target.value })}
        />
      </label>

      <label className="block text-sm font-medium text-slate-700">
        {t('timesheet.description')}
        <textarea
          rows={4}
          className={inputClass}
          value={form.description}
          onChange={(event) => onFormChange({ ...form, description: event.target.value })}
        />
      </label>

      <button type="submit" disabled={submitting} className={primaryButtonClass}>
        {submitting ? t('common.saving') : t('timesheet.saveTime')}
      </button>

      {message && <Alert variant={message.type}>{message.text}</Alert>}
    </form>
  )
}
