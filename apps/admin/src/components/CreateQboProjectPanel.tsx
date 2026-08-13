/**
 * @file Admin form to create QuickBooks projects under a parent client.
 */

import {
  Button,
  cardClass,
  LazySearchCombobox,
  sectionHelpClass,
  sectionTitleClass,
  TextField,
  useLocale,
} from '@ellr/ui'
import type { QboCustomerOption } from '../hooks/useCreateQboProject'

type CreateQboProjectPanelProps = {
  connected: boolean
  customers: QboCustomerOption[]
  loadingCustomers: boolean
  customersLoaded: boolean
  selectedCustomer: QboCustomerOption | null
  displayName: string
  creating: boolean
  onCustomerChange: (customer: QboCustomerOption | null) => void
  onCustomerDropdownOpen: () => void
  onCustomerDropdownClose: () => void
  onDisplayNameChange: (value: string) => void
  onSubmit: (event: React.FormEvent) => void
}

/**
 * Administrator form to create a QuickBooks project for a selected client.
 * @param props Project create state and handlers from `useCreateQboProject`.
 * @returns Project creation card content.
 */
export function CreateQboProjectPanel({
  connected,
  customers,
  loadingCustomers,
  customersLoaded,
  selectedCustomer,
  displayName,
  creating,
  onCustomerChange,
  onCustomerDropdownOpen,
  onCustomerDropdownClose,
  onDisplayNameChange,
  onSubmit,
}: CreateQboProjectPanelProps) {
  const { t } = useLocale()

  if (!connected) {
    return (
      <section className={cardClass}>
        <h2 className={sectionTitleClass}>{t('admin.createProjectTitle')}</h2>
        <p className={sectionHelpClass}>{t('admin.createProjectConnectFirst')}</p>
      </section>
    )
  }

  const canSubmit = selectedCustomer !== null && displayName.trim() !== '' && !creating

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleClass}>{t('admin.createProjectTitle')}</h2>
      <p className={sectionHelpClass}>{t('admin.createProjectHelp')}</p>

      <form className="mt-6 space-y-4 rounded-[9px] border border-brand-border p-4" onSubmit={onSubmit}>
        <LazySearchCombobox
          label={t('admin.selectProjectClient')}
          placeholder={t('admin.chooseProjectClient')}
          searchPlaceholder={t('admin.searchClientsPlaceholder')}
          loadingLabel={t('admin.loadingClients')}
          emptyLabel={t('admin.noClientsAvailable')}
          noResultsLabel={t('admin.noClientSearchResults')}
          value={selectedCustomer}
          options={customers}
          loading={loadingCustomers}
          loaded={customersLoaded}
          onLoad={onCustomerDropdownOpen}
          onClose={onCustomerDropdownClose}
          onChange={onCustomerChange}
          getOptionValue={(customer) => customer.id}
          getOptionLabel={(customer) => customer.display_name}
        />

        <TextField
          label={t('admin.projectName')}
          value={displayName}
          onChange={(event) => onDisplayNameChange(event.target.value)}
          placeholder={t('admin.projectNamePlaceholder')}
          autoComplete="off"
        />

        <Button type="submit" disabled={!canSubmit}>
          {creating ? t('admin.creatingProject') : t('admin.createProject')}
        </Button>
      </form>
    </section>
  )
}
