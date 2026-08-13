/**
 * @file Shared QBO picker + field controls for editing a local time entry.
 */

import type { useTimeEntryEditForm } from '../hooks/useTimeEntryEditForm'
import { useLocale } from '../i18n/LocaleProvider'
import { CheckboxField } from './CheckboxField'
import { LazySearchCombobox } from './LazySearchCombobox'
import { TextAreaField } from './TextAreaField'
import { TextField } from './TextField'

type TimeEntryEditFieldsProps = {
  form: ReturnType<typeof useTimeEntryEditForm>
}

/**
 * Client, project, service, schedule, description, and billable fields for time entry edits.
 * @param props Shared edit-form state from {@link useTimeEntryEditForm}.
 * @returns Form field markup.
 */
export function TimeEntryEditFields({ form }: TimeEntryEditFieldsProps) {
  const { t } = useLocale()

  return (
    <div className="space-y-4">
      <LazySearchCombobox
        label={t('timesheet.client')}
        placeholder={t('timesheet.addClient')}
        searchPlaceholder={t('timesheet.searchClients')}
        loadingLabel={t('timesheet.loadingClients')}
        emptyLabel={t('timesheet.noClients')}
        noResultsLabel={t('timesheet.noClientResults')}
        value={form.customer}
        options={form.customersSelect.items}
        loading={form.customersSelect.loading}
        loaded={form.customersSelect.loaded}
        onLoad={form.customersSelect.onOpen}
        onClose={form.customersSelect.onClose}
        onChange={(value) => {
          form.setCustomer(value)
          form.setProject(null)
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
        value={form.project}
        options={form.projectsSelect.items}
        loading={form.projectsSelect.loading}
        loaded={form.projectsSelect.loaded}
        onLoad={form.projectsSelect.onOpen}
        onClose={form.projectsSelect.onClose}
        onChange={form.setProject}
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
        value={form.service}
        options={form.servicesSelect.items}
        loading={form.servicesSelect.loading}
        loaded={form.servicesSelect.loaded}
        onLoad={form.servicesSelect.onOpen}
        onClose={form.servicesSelect.onClose}
        onChange={form.setService}
        getOptionValue={(option) => option.id}
        getOptionLabel={(option) => option.display_name}
      />
      <div className="grid gap-3 md:grid-cols-2">
        <TextField
          label={t('timeActivity.start')}
          type="datetime-local"
          value={form.startValue}
          disabled={form.busy}
          onChange={(event) => form.setStartValue(event.target.value)}
        />
        <TextField
          label={t('timeActivity.end')}
          type="datetime-local"
          value={form.endValue}
          disabled={form.busy}
          onChange={(event) => form.setEndValue(event.target.value)}
        />
      </div>
      <TextAreaField
        label={t('timeActivity.description')}
        value={form.description}
        disabled={form.busy}
        onChange={(event) => form.setDescription(event.target.value)}
      />
      <CheckboxField
        label={t('timeActivity.billable')}
        checked={form.isBillable}
        disabled={form.busy}
        onChange={form.setIsBillable}
      />
    </div>
  )
}
