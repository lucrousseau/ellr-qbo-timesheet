/**
 * @file QBO account and party pickers for the expense form.
 */

import type { QboPickerOption } from '@ellr/api-client'
import { LazySearchCombobox } from '../LazySearchCombobox'
import { useLocale } from '../../i18n/LocaleProvider'

type PickerSelect = {
  items: QboPickerOption[]
  loading: boolean
  loaded: boolean
  onOpen: () => void | Promise<void>
  onClose: () => void
}

type PickerConfig = {
  labelKey: string
  placeholder: string
  searchKey: string
  loadingKey: string
  emptyKey: string
  noResultsKey: string
  value: QboPickerOption | null
  select: PickerSelect
  onChange: (value: QboPickerOption | null) => void
  disabled?: boolean
}

type ExpenseFormPickersProps = {
  paymentAccount: QboPickerOption | null
  expenseAccount: QboPickerOption | null
  vendor: QboPickerOption | null
  customer: QboPickerOption | null
  project: QboPickerOption | null
  paymentAccountsSelect: PickerSelect
  expenseAccountsSelect: PickerSelect
  vendorsSelect: PickerSelect
  customersSelect: PickerSelect
  projectsSelect: PickerSelect
  onPaymentAccountChange: (value: QboPickerOption | null) => void
  onExpenseAccountChange: (value: QboPickerOption | null) => void
  onVendorChange: (value: QboPickerOption | null) => void
  onCustomerChange: (value: QboPickerOption | null) => void
  onProjectChange: (value: QboPickerOption | null) => void
}

/**
 * Lazy QBO pickers for payment/expense accounts, vendor, customer, and project.
 * @param props Selected values and lazy-select state.
 * @returns Combobox fields for expense attribution.
 */
export function ExpenseFormPickers(props: ExpenseFormPickersProps) {
  const { t } = useLocale()
  const pickers: PickerConfig[] = [
    {
      labelKey: 'expense.paymentAccount',
      placeholder: t('expense.choosePaymentAccount'),
      searchKey: 'expense.searchPaymentAccounts',
      loadingKey: 'expense.loadingPaymentAccounts',
      emptyKey: 'expense.noPaymentAccounts',
      noResultsKey: 'expense.noPaymentAccountResults',
      value: props.paymentAccount,
      select: props.paymentAccountsSelect,
      onChange: props.onPaymentAccountChange,
    },
    {
      labelKey: 'expense.expenseAccount',
      placeholder: t('expense.chooseExpenseAccount'),
      searchKey: 'expense.searchExpenseAccounts',
      loadingKey: 'expense.loadingExpenseAccounts',
      emptyKey: 'expense.noExpenseAccounts',
      noResultsKey: 'expense.noExpenseAccountResults',
      value: props.expenseAccount,
      select: props.expenseAccountsSelect,
      onChange: props.onExpenseAccountChange,
    },
    {
      labelKey: 'expense.vendor',
      placeholder: t('expense.chooseVendor'),
      searchKey: 'expense.searchVendors',
      loadingKey: 'expense.loadingVendors',
      emptyKey: 'expense.noVendors',
      noResultsKey: 'expense.noVendorResults',
      value: props.vendor,
      select: props.vendorsSelect,
      onChange: props.onVendorChange,
    },
    {
      labelKey: 'expense.customer',
      placeholder: t('expense.chooseCustomer'),
      searchKey: 'expense.searchCustomers',
      loadingKey: 'expense.loadingCustomers',
      emptyKey: 'expense.noCustomers',
      noResultsKey: 'expense.noCustomerResults',
      value: props.customer,
      select: props.customersSelect,
      onChange: props.onCustomerChange,
    },
    {
      labelKey: 'expense.project',
      placeholder: props.customer ? t('expense.chooseProject') : t('expense.selectCustomerFirst'),
      searchKey: 'expense.searchProjects',
      loadingKey: 'expense.loadingProjects',
      emptyKey: 'expense.noProjects',
      noResultsKey: 'expense.noProjectResults',
      value: props.project,
      select: props.projectsSelect,
      onChange: props.onProjectChange,
      disabled: !props.customer,
    },
  ]

  return (
    <>
      {pickers.map((picker) => (
        <LazySearchCombobox
          key={picker.labelKey}
          label={t(picker.labelKey)}
          placeholder={picker.placeholder}
          searchPlaceholder={t(picker.searchKey)}
          loadingLabel={t(picker.loadingKey)}
          emptyLabel={t(picker.emptyKey)}
          noResultsLabel={t(picker.noResultsKey)}
          value={picker.value}
          options={picker.select.items}
          loading={picker.select.loading}
          loaded={picker.select.loaded}
          onLoad={picker.select.onOpen}
          onClose={picker.select.onClose}
          onChange={picker.onChange}
          getOptionValue={(option) => option.id}
          getOptionLabel={(option) => option.display_name}
          isOptionDisabled={picker.disabled ? () => true : undefined}
        />
      ))}
    </>
  )
}
