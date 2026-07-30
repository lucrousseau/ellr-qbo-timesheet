/**
 * @file Card list for reviewing and approving pending time entries.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'
import { useLocale } from '../i18n/LocaleProvider'

type TimeEntryApprovalListProps = {
  entries: TimeActivityRow[]
  reviewingId: string | null
  displayTimezone?: string | null
  onApprove: (id: string) => Promise<void>
  onReject: (id: string, reason?: string | null) => Promise<void>
}

/**
 * Renders pending time entries as review cards with visible approve and reject actions.
 * @param props Pending entries and review handlers.
 * @returns Approval card list.
 */
export function TimeEntryApprovalList({
  entries,
  reviewingId,
  displayTimezone = null,
  onApprove,
  onReject,
}: TimeEntryApprovalListProps) {
  const { t, locale } = useLocale()

  return (
    <ul className="space-y-4">
      {entries.map((entry) => (
        <li
          key={entry.id}
          className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="min-w-0 space-y-2 text-sm text-slate-700">
              <p className="text-base font-medium text-slate-900">
                {entry.employeeName ?? t('timeActivity.noValue')}
              </p>
              <p>
                {formatEntryDateTime(entry.startTime, locale, displayTimezone) || t('timeActivity.noValue')}
                {' '}
                {t('timeActivity.rangeSeparator')}
                {' '}
                {formatEntryDateTime(entry.endTime, locale, displayTimezone) || t('timeActivity.noValue')}
                {' '}
                ({formatEntryDuration(entry.durationSeconds)})
              </p>
              <p>
                {entry.customerName ?? t('timeActivity.noValue')}
                {' '}
                ·
                {' '}
                {entry.serviceName ?? t('timeActivity.noValue')}
              </p>
              {entry.description?.trim() ? <p>{entry.description}</p> : null}
              <p className="text-slate-500">
                {entry.isBillable ? t('timeActivity.billableYes') : t('timeActivity.billableNo')}
              </p>
            </div>

            <TimeEntryApprovalActions
              entryId={entry.id}
              reviewing={reviewingId === entry.id}
              onApprove={onApprove}
              onReject={onReject}
            />
          </div>
        </li>
      ))}
    </ul>
  )
}
