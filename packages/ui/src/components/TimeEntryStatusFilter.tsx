/**
 * @file Status filter select for unified time entry tables.
 */

import { StaticSelect } from './StaticSelect'
import { useLocale } from '../i18n/LocaleProvider'
import {
  TIME_ENTRY_STATUS_FILTERS,
  type TimeEntryStatusFilter,
} from '../utils/filterTimeEntriesByStatus'

type TimeEntryStatusFilterProps = {
  value: TimeEntryStatusFilter
  onChange: (value: TimeEntryStatusFilter) => void
}

/**
 * Status filter select for time entry tables.
 * @param props Controlled filter value and change handler.
 * @returns Compact status filter field.
 */
export function TimeEntryStatusFilter({ value, onChange }: TimeEntryStatusFilterProps) {
  const { t } = useLocale()

  return (
    <div className="max-w-xs">
      <StaticSelect
        label={t('timeActivity.statusFilter')}
        value={value}
        options={TIME_ENTRY_STATUS_FILTERS}
        onChange={onChange}
        getOptionValue={(option) => option}
        getOptionLabel={(option) => labelForStatusFilter(option, t)}
      />
    </div>
  )
}

/**
 * Resolves the localized label for one status filter option.
 * @param option Selected filter value.
 * @param t Locale lookup function.
 * @returns Localized option label.
 */
function labelForStatusFilter(
  option: TimeEntryStatusFilter,
  t: (key: string) => string,
): string {
  if (option === 'not_approved') {
    return t('timeActivity.filterNotApproved')
  }
  if (option === 'all') {
    return t('timeActivity.filterAll')
  }
  if (option === 'pending') {
    return t('timeActivity.statusPending')
  }
  if (option === 'approved') {
    return t('timeActivity.statusApproved')
  }
  return t('timeActivity.statusRejected')
}
