/**
 * @file Ticket, description, and billable fields for the timer workflow.
 */

import { CheckboxField, TextAreaField, TextField, useLocale } from '@ellr/ui'

type TimerNotesFieldsProps = {
  ticketKey: string
  description: string
  isBillable: boolean
  onTicketKeyChange: (value: string) => void
  onTicketKeyBlur: () => void
  onDescriptionChange: (value: string) => void
  onDescriptionBlur: () => void
  onBillableChange: (value: boolean) => void
}

/**
 * Capture fields for ticket key, work notes, and billable flag.
 * @param props Controlled field values and change handlers.
 * @returns Timer notes form section.
 */
export function TimerNotesFields({
  ticketKey,
  description,
  isBillable,
  onTicketKeyChange,
  onTicketKeyBlur,
  onDescriptionChange,
  onDescriptionBlur,
  onBillableChange,
}: TimerNotesFieldsProps) {
  const { t } = useLocale()

  return (
    <>
      <TextField
        label={t('timesheet.ticket')}
        placeholder={t('timesheet.ticketPlaceholder')}
        hint={t('timesheet.ticketHelp')}
        value={ticketKey}
        onChange={(event) => onTicketKeyChange(event.target.value)}
        onBlur={onTicketKeyBlur}
      />

      <TextAreaField
        label={t('timesheet.description')}
        placeholder={t('timesheet.descriptionPlaceholder')}
        rows={4}
        value={description}
        onChange={(event) => onDescriptionChange(event.target.value)}
        onBlur={onDescriptionBlur}
      />

      <CheckboxField
        label={t('timesheet.billable')}
        checked={isBillable}
        onChange={onBillableChange}
      />
    </>
  )
}
