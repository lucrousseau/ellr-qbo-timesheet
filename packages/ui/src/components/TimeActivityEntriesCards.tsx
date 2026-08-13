/**
 * @file Stacked time activity list for narrow timesheet layouts.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import { useLocale } from '../i18n/LocaleProvider'
import { formatApprovalStatus } from './timeActivityApprovalStatus'
import { TimeActivityDraftActions } from './TimeActivityDraftActions'
import { formatEntryDateTimeRange, formatEntryDuration } from './timeActivityDisplay'

/** Props for {@link TimeActivityEntriesCards}. */
export type TimeActivityEntriesCardsProps = {
  entries: TimeActivityRow[]
  showApprovalStatus: boolean
  displayTimezone: string | null
  draftActions: boolean
  actionEntryId: string | null
  onEditDraft?: (entry: TimeActivityRow) => void
  onDeleteDraft?: (id: string) => Promise<void>
}

/**
 * Renders time entries as stacked rows with wrapping action buttons.
 * @param props Entries and optional draft action handlers.
 * @returns Accessible list of time entry summaries.
 */
export function TimeActivityEntriesCards({
  entries,
  showApprovalStatus,
  displayTimezone,
  draftActions,
  actionEntryId,
  onEditDraft,
  onDeleteDraft,
}: TimeActivityEntriesCardsProps) {
  const { t, locale } = useLocale()

  return (
    <ul className="mt-4 divide-y divide-brand-border">
      {entries.map((entry) => (
        <li key={entry.id} className="space-y-3 py-4 first:pt-0 last:pb-0">
          <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <p className="text-sm font-medium text-brand-primary">
              {entry.customerName ?? t('timeActivity.noValue')}
            </p>
            <p className="text-sm font-semibold tabular-nums text-brand-primary">
              {formatEntryDuration(entry.durationSeconds)}
            </p>
          </div>
          <p className="text-sm text-brand-muted">
            {formatEntryDateTimeRange(
              entry.startTime,
              entry.endTime,
              locale,
              displayTimezone,
              t('timeActivity.noValue'),
            )}
          </p>
          <dl className="grid gap-1 text-sm text-brand-primary sm:grid-cols-2">
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.service')}
              </dt>
              <dd>{entry.serviceName ?? t('timeActivity.noValue')}</dd>
            </div>
            {showApprovalStatus ? (
              <div>
                <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                  {t('timeActivity.status')}
                </dt>
                <dd>{formatApprovalStatus(entry.approvalStatus, t)}</dd>
              </div>
            ) : null}
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.billable')}
              </dt>
              <dd>
                {entry.billableLocked
                  ? t('timeActivity.billed')
                  : entry.isBillable
                    ? t('timeActivity.billableYes')
                    : t('timeActivity.billableNo')}
              </dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.description')}
              </dt>
              <dd className="whitespace-pre-wrap break-words">
                {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
              </dd>
            </div>
          </dl>
          {draftActions ? (
            <TimeActivityDraftActions
              entry={entry}
              busy={actionEntryId === entry.id}
              onEditDraft={onEditDraft}
              onDeleteDraft={onDeleteDraft}
            />
          ) : null}
        </li>
      ))}
    </ul>
  )
}
