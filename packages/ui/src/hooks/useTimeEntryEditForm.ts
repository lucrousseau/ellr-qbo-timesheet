/**
 * @file Shared form state and QBO pickers for editing a local time entry.
 */

import {
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  fromDateTimeLocalValue,
  toDateTimeLocalValue,
  type QboPickerOption,
  type TimeActivityRow,
  type TimeEntryUpdatePayload,
} from '@ellr/api-client'
import { useCallback, useEffect, useState } from 'react'
import { useFlashMessage } from './useFlashMessage'
import { useGuardedAction } from './useGuardedAction'
import { useLazyApiSelect } from './useLazyApiSelect'
import { useLocale } from '../i18n/LocaleProvider'
import { optionFromRef, splitCustomerProjectLabel } from '../utils/timeEntryEditOptions'

export { optionFromRef, splitCustomerProjectLabel } from '../utils/timeEntryEditOptions'

type UseTimeEntryEditFormOptions = {
  entry: TimeActivityRow | null
  saving: boolean
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Owns time-entry edit fields and lazy QBO picker fetches for draft and approval editors.
 * @param options Entry being edited and save handler.
 * @returns Form state, picker selects, and save action.
 */
export function useTimeEntryEditForm({
  entry,
  saving,
  onSave,
}: UseTimeEntryEditFormOptions) {
  const { t } = useLocale()
  const { showError } = useFlashMessage()
  const [customer, setCustomer] = useState<QboPickerOption | null>(null)
  const [project, setProject] = useState<QboPickerOption | null>(null)
  const [service, setService] = useState<QboPickerOption | null>(null)
  const [startValue, setStartValue] = useState('')
  const [endValue, setEndValue] = useState('')
  const [description, setDescription] = useState('')
  const [isBillable, setIsBillable] = useState(false)

  useEffect(() => {
    if (!entry) {
      return
    }

    const { customerLabel, projectLabel } = splitCustomerProjectLabel(entry.customerName)
    setCustomer(optionFromRef(entry.customerRef, customerLabel))
    setProject(optionFromRef(entry.projectRef, projectLabel))
    setService(optionFromRef(entry.itemRef, entry.serviceName))
    setStartValue(toDateTimeLocalValue(entry.startTime))
    setEndValue(toDateTimeLocalValue(entry.endTime))
    setDescription(entry.description ?? '')
    setIsBillable(entry.isBillable)
  }, [entry])

  const fetchCustomers = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchQboCustomers({ refresh, signal }),
    [],
  )
  const fetchProjects = useCallback(
    (refresh: boolean, signal: AbortSignal) =>
      customer ? fetchQboProjects(customer.id, { refresh, signal }) : Promise.resolve([]),
    [customer],
  )
  const fetchServices = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchQboServices({ refresh, signal }),
    [],
  )

  const customersSelect = useLazyApiSelect({
    enabled: entry !== null,
    fetch: fetchCustomers,
    onError: showError,
    errorMessage: t('timesheet.loadCustomersFailed'),
  })
  const projectsSelect = useLazyApiSelect({
    enabled: entry !== null && customer !== null,
    fetch: fetchProjects,
    onError: showError,
    errorMessage: t('timesheet.loadProjectsFailed'),
  })
  const servicesSelect = useLazyApiSelect({
    enabled: entry !== null,
    fetch: fetchServices,
    onError: showError,
    errorMessage: t('timesheet.loadServicesFailed'),
  })

  const { run: handleSave, pending } = useGuardedAction(async () => {
    if (!entry) {
      return
    }

    const startTime = fromDateTimeLocalValue(startValue)
    const endTime = fromDateTimeLocalValue(endValue)

    if (!startTime || !endTime) {
      return
    }

    await onSave(entry.id, {
      customer_ref: customer?.id ?? null,
      project_ref: project?.id ?? null,
      item_ref: service?.id ?? null,
      start_time: startTime,
      end_time: endTime,
      description: description.trim() === '' ? null : description,
      is_billable: isBillable,
    })
  })

  return {
    open: entry !== null,
    busy: saving || pending,
    customer,
    project,
    service,
    startValue,
    endValue,
    description,
    isBillable,
    customersSelect,
    projectsSelect,
    servicesSelect,
    setCustomer,
    setProject,
    setService,
    setStartValue,
    setEndValue,
    setDescription,
    setIsBillable,
    handleSave,
  }
}
