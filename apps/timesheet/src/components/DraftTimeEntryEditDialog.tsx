/**
 * @file Dialog to edit a draft time entry including client, project, and service.
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
import {
  AppDialog,
  Button,
  CheckboxField,
  LazySearchCombobox,
  TextAreaField,
  TextField,
  useFlashMessage,
  useGuardedAction,
  useLazyApiSelect,
  useLocale,
} from '@ellr/ui'
import { useCallback, useEffect, useState } from 'react'

type DraftTimeEntryEditDialogProps = {
  entry: TimeActivityRow | null
  saving: boolean
  onClose: () => void
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Builds a picker option from a stored ref and display label.
 * @param ref QuickBooks entity ref.
 * @param label Display label fallback.
 * @returns Picker option or null.
 */
function optionFromRef(ref: string | null | undefined, label: string | null | undefined): QboPickerOption | null {
  if (!ref) {
    return null
  }

  return { id: ref, display_name: label ?? ref }
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

    setCustomer(optionFromRef(entry.customerRef, entry.customerName))
    setProject(optionFromRef(entry.projectRef, null))
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
      customer
        ? fetchQboProjects(customer.id, { refresh, signal })
        : Promise.resolve([]),
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

  const busy = saving || pending

  return (
    <AppDialog
      open={entry !== null}
      title={t('timesheet.editDraftTitle')}
      onClose={onClose}
      panelClassName="w-full max-w-xl rounded-xl border border-brand-border bg-white p-6 shadow-xl"
    >
      <div className="space-y-4">
        <LazySearchCombobox
          label={t('timesheet.client')}
          placeholder={t('timesheet.addClient')}
          searchPlaceholder={t('timesheet.searchClients')}
          loadingLabel={t('timesheet.loadingClients')}
          emptyLabel={t('timesheet.noClients')}
          noResultsLabel={t('timesheet.noClientResults')}
          value={customer}
          options={customersSelect.items}
          loading={customersSelect.loading}
          loaded={customersSelect.loaded}
          onLoad={customersSelect.onOpen}
          onClose={customersSelect.onClose}
          onChange={(value) => {
            setCustomer(value)
            setProject(null)
          }}
          getOptionValue={(option) => option.id}
          getOptionLabel={(option) => option.display_name}
        />
        <LazySearchCombobox
          label={t('timesheet.project')}
          placeholder={t('timesheet.addProject')}
          searchPlaceholder={t('timesheet.searchProjects')}
          loadingLabel={t('timesheet.loadingProjects')}
          emptyLabel={t('timesheet.noProjects')}
          noResultsLabel={t('timesheet.noProjectResults')}
          value={project}
          options={projectsSelect.items}
          loading={projectsSelect.loading}
          loaded={projectsSelect.loaded}
          onLoad={projectsSelect.onOpen}
          onClose={projectsSelect.onClose}
          onChange={setProject}
          getOptionValue={(option) => option.id}
          getOptionLabel={(option) => option.display_name}
        />
        <LazySearchCombobox
          label={t('timesheet.service')}
          placeholder={t('timesheet.addService')}
          searchPlaceholder={t('timesheet.searchServices')}
          loadingLabel={t('timesheet.loadingServices')}
          emptyLabel={t('timesheet.noServices')}
          noResultsLabel={t('timesheet.noServiceResults')}
          value={service}
          options={servicesSelect.items}
          loading={servicesSelect.loading}
          loaded={servicesSelect.loaded}
          onLoad={servicesSelect.onOpen}
          onClose={servicesSelect.onClose}
          onChange={setService}
          getOptionValue={(option) => option.id}
          getOptionLabel={(option) => option.display_name}
        />
        <TextField
          label={t('timeActivity.start')}
          type="datetime-local"
          value={startValue}
          onChange={(event) => setStartValue(event.target.value)}
        />
        <TextField
          label={t('timeActivity.end')}
          type="datetime-local"
          value={endValue}
          onChange={(event) => setEndValue(event.target.value)}
        />
        <TextAreaField
          label={t('timeActivity.description')}
          value={description}
          onChange={(event) => setDescription(event.target.value)}
        />
        <CheckboxField
          label={t('timeActivity.billable')}
          checked={isBillable}
          onChange={setIsBillable}
        />
        <div className="flex justify-end gap-3">
          <Button type="button" variant="secondary" disabled={busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={busy} onClick={() => void handleSave()}>
            {busy ? t('timeActivity.saving') : t('timeActivity.save')}
          </Button>
        </div>
      </div>
    </AppDialog>
  )
}
