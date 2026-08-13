/**
 * @file Filter controls for pending time entry approval tables.
 */

import { StaticSelect } from './StaticSelect'
import { TextField } from './TextField'
import { useLocale } from '../i18n/LocaleProvider'
import {
  TIME_ENTRY_BILLABLE_FILTERS,
  type TimeEntryBillableFilter,
  type TimeEntryReviewFilters,
} from '../utils/filterTimeEntriesForReview'

type TimeEntryApprovalsFiltersProps = {
  filters: TimeEntryReviewFilters
  employeeOptions: readonly string[]
  clientOptions: readonly string[]
  onChange: (next: TimeEntryReviewFilters) => void
}

/**
 * Manager-oriented filters for employee, client, billable, and date range.
 * @param props Controlled filter state and facet options from loaded rows.
 * @returns Filter toolbar for the approvals table.
 */
export function TimeEntryApprovalsFilters({
  filters,
  employeeOptions,
  clientOptions,
  onChange,
}: TimeEntryApprovalsFiltersProps) {
  const { t } = useLocale()
  const employees = ['', ...employeeOptions]
  const clients = ['', ...clientOptions]

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <StaticSelect
        label={t('timeActivity.filterEmployee')}
        value={filters.employee}
        options={employees}
        onChange={(employee) => onChange({ ...filters, employee })}
        getOptionValue={(option) => option}
        getOptionLabel={(option) =>
          option === '' ? t('timeActivity.filterAllEmployees') : option
        }
      />
      <StaticSelect
        label={t('timeActivity.filterClient')}
        value={filters.client}
        options={clients}
        onChange={(client) => onChange({ ...filters, client })}
        getOptionValue={(option) => option}
        getOptionLabel={(option) =>
          option === '' ? t('timeActivity.filterAllClients') : option
        }
      />
      <StaticSelect
        label={t('timeActivity.filterBillable')}
        value={filters.billable}
        options={TIME_ENTRY_BILLABLE_FILTERS}
        onChange={(billable: TimeEntryBillableFilter) => onChange({ ...filters, billable })}
        getOptionValue={(option) => option}
        getOptionLabel={(option) => labelForBillableFilter(option, t)}
      />
      <TextField
        label={t('timeActivity.filterDateFrom')}
        type="date"
        value={filters.dateFrom}
        onChange={(event) => onChange({ ...filters, dateFrom: event.target.value })}
      />
      <TextField
        label={t('timeActivity.filterDateTo')}
        type="date"
        value={filters.dateTo}
        onChange={(event) => onChange({ ...filters, dateTo: event.target.value })}
      />
    </div>
  )
}

/**
 * Resolves the localized label for one billable filter option.
 * @param option Selected billable filter.
 * @param t Locale lookup function.
 * @returns Localized option label.
 */
function labelForBillableFilter(
  option: TimeEntryBillableFilter,
  t: (key: string) => string,
): string {
  if (option === 'billable') {
    return t('timeActivity.billableYes')
  }
  if (option === 'non_billable') {
    return t('timeActivity.billableNo')
  }
  return t('timeActivity.filterAllBillable')
}
