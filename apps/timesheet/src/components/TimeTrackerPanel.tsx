/**
 * @file Primary timer workflow UI for client, project, service, and time logging.
 */

import type { FlashMessage } from '@ellr/ui'
import {
  Alert,
  Button,
  cardClass,
  LazySearchCombobox,
  LoadingScreen,
  TextAreaField,
  useLocale,
} from '@ellr/ui'
import type { QboPickerOption } from '../hooks/useTimeTracker'
import { TimerDisplay } from './TimerDisplay'

type PickerSelect = {
  items: QboPickerOption[]
  loading: boolean
  loaded: boolean
  onOpen: () => void | Promise<void>
  onClose: () => void
}

type TimeTrackerPanelProps = {
  loading: boolean
  headerLabel: string
  customer: QboPickerOption | null
  project: QboPickerOption | null
  service: QboPickerOption | null
  description: string
  elapsedSeconds: number
  isRunning: boolean
  logging: boolean
  discarding: boolean
  message: FlashMessage | null
  customersSelect: PickerSelect
  projectsSelect: PickerSelect
  servicesSelect: PickerSelect
  onCustomerChange: (value: QboPickerOption | null) => void
  onProjectChange: (value: QboPickerOption | null) => void
  onServiceChange: (value: QboPickerOption | null) => void
  onDescriptionChange: (value: string) => void
  onDescriptionBlur: () => void
  onToggleTimer: () => void
  onLogTime: () => void | Promise<void>
  onDiscard: () => void | Promise<void>
}

/**
 * Timer panel with QBO pickers, play/pause, log time, and discard actions.
 * @param props Timer state, picker data, and event handlers.
 * @returns Timer workflow card.
 */
export function TimeTrackerPanel({
  loading,
  headerLabel,
  customer,
  project,
  service,
  description,
  elapsedSeconds,
  isRunning,
  logging,
  discarding,
  message,
  customersSelect,
  projectsSelect,
  servicesSelect,
  onCustomerChange,
  onProjectChange,
  onServiceChange,
  onDescriptionChange,
  onDescriptionBlur,
  onToggleTimer,
  onLogTime,
  onDiscard,
}: TimeTrackerPanelProps) {
  const { t } = useLocale()

  if (loading) {
    return <LoadingScreen />
  }

  return (
    <section className={`space-y-5 ${cardClass}`}>
      <header className="rounded-lg bg-slate-100 px-4 py-3 text-center text-sm font-medium text-slate-700">
        {headerLabel}
      </header>

      <TimerDisplay elapsedSeconds={elapsedSeconds} isRunning={isRunning} onToggle={onToggleTimer} />

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
        onChange={onCustomerChange}
        getOptionValue={(option) => option.id}
        getOptionLabel={(option) => option.display_name}
      />

      <LazySearchCombobox
        label={t('timesheet.project')}
        placeholder={t('timesheet.addProject')}
        searchPlaceholder={t('timesheet.searchProjects')}
        loadingLabel={t('timesheet.loadingProjects')}
        emptyLabel={customer ? t('timesheet.noProjects') : t('timesheet.selectClientFirst')}
        noResultsLabel={t('timesheet.noProjectResults')}
        value={project}
        options={projectsSelect.items}
        loading={projectsSelect.loading}
        loaded={projectsSelect.loaded}
        onLoad={projectsSelect.onOpen}
        onClose={projectsSelect.onClose}
        onChange={onProjectChange}
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
        onChange={onServiceChange}
        getOptionValue={(option) => option.id}
        getOptionLabel={(option) => option.display_name}
      />

      <TextAreaField
        label={t('timesheet.description')}
        placeholder={t('timesheet.descriptionPlaceholder')}
        rows={4}
        value={description}
        onChange={(event) => onDescriptionChange(event.target.value)}
        onBlur={onDescriptionBlur}
      />

      <Button type="button" disabled={logging || elapsedSeconds <= 0} onClick={() => void onLogTime()}>
        {logging ? t('common.saving') : t('timesheet.logTime')}
      </Button>

      <div className="text-center">
        <Button
          type="button"
          variant="link"
          disabled={discarding || (elapsedSeconds <= 0 && !customer && !project && !service && !description)}
          onClick={() => void onDiscard()}
        >
          {t('timesheet.discard')}
        </Button>
      </div>

      {message && <Alert variant={message.type}>{message.text}</Alert>}
    </section>
  )
}
