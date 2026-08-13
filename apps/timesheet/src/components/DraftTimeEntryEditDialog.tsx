/**
 * @file Dialog to edit a draft time entry including client, project, and service.
 */

import {
  AppDialog,
  Button,
  CheckboxField,
  LazySearchCombobox,
  TextAreaField,
  TextField,
  useLocale,
} from '@ellr/ui'
import {
  useDraftTimeEntryEditForm,
  type TimeActivityRow,
  type TimeEntryUpdatePayload,
} from '../hooks/useDraftTimeEntryEditForm'

type DraftTimeEntryEditDialogProps = {
  entry: TimeActivityRow | null
  saving: boolean
  onClose: () => void
  onSave: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
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
  const form = useDraftTimeEntryEditForm({ entry, saving, onClose, onSave })

  return (
    <AppDialog
      open={form.open}
      title={t('timesheet.editDraftTitle')}
      onClose={form.onClose}
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
        <TextField
          label={t('timeActivity.start')}
          type="datetime-local"
          value={form.startValue}
          onChange={(event) => form.setStartValue(event.target.value)}
        />
        <TextField
          label={t('timeActivity.end')}
          type="datetime-local"
          value={form.endValue}
          onChange={(event) => form.setEndValue(event.target.value)}
        />
        <TextAreaField
          label={t('timeActivity.description')}
          value={form.description}
          onChange={(event) => form.setDescription(event.target.value)}
        />
        <CheckboxField
          label={t('timeActivity.billable')}
          checked={form.isBillable}
          onChange={form.setIsBillable}
        />
        <div className="flex justify-end gap-3">
          <Button type="button" variant="secondary" disabled={form.busy} onClick={form.onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" disabled={form.busy} onClick={() => void form.handleSave()}>
            {form.busy ? t('timeActivity.saving') : t('timeActivity.save')}
          </Button>
        </div>
      </div>
    </AppDialog>
  )
}
