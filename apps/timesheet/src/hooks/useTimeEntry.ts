/**
 * @file Timesheet time entry form state and submit handler.
 */

import { useState } from 'react'
import { createTimeActivity } from '@ellr/api-client'
import { getApiErrorMessage, useFlashMessage, useLocale } from '@ellr/ui'

type TimeActivityForm = {
  start_time: string
  end_time: string
  description: string
}

/**
 * Time entry form state and QuickBooks submit flow for the timesheet app.
 * @returns Form state, flash messages, and submit handler.
 */
export function useTimeEntry() {
  const { locale, t } = useLocale()
  const { message, clearMessage, showError, showSuccess } = useFlashMessage()
  const [form, setForm] = useState<TimeActivityForm>({
    start_time: '',
    end_time: '',
    description: '',
  })
  const [submitting, setSubmitting] = useState(false)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    clearMessage()
    setSubmitting(true)

    const payload: { start_time: string; end_time: string; description?: string } = {
      start_time: form.start_time,
      end_time: form.end_time,
    }

    if (form.description) {
      payload.description = form.description
    }

    try {
      await createTimeActivity(payload)
      showSuccess(t('timesheet.savedSuccess'))
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('timesheet.saveFailed'), locale))
    } finally {
      setSubmitting(false)
    }
  }

  return {
    form,
    setForm,
    submitting,
    message,
    clearMessage,
    submit,
  }
}
