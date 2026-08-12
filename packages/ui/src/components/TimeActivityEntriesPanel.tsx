/**
 * @file Read-only and editable time activity tables with pagination.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { Alert } from './Alert'
import { Button } from './Button'
import { LoadingScreen } from './LoadingScreen'
import { cardClass, sectionTitleCompactClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { TimeActivityEditableRow } from './TimeActivityEditableRow'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'

/**
 * Maps a time entry approval status to a localized label.
 * @param status Approval status from the API row.
 * @param t Locale lookup function.
 * @returns Localized status label.
 */
function formatApprovalStatus(
  status: TimeActivityRow['approvalStatus'],
  t: (key: string) => string,
): string {
  if (status === 'pending') {
    return t('timeActivity.statusPending')
  }

  if (status === 'approved') {
    return t('timeActivity.statusApproved')
  }

  if (status === 'rejected') {
    return t('timeActivity.statusRejected')
  }

  return t('timeActivity.noValue')
}

/** Props for {@link TimeActivityEntriesPanel}. */
export type TimeActivityEntriesPanelProps = {
  title?: string
  embedded?: boolean
  entries: TimeActivityRow[]
  loading: boolean
  error: string | null
  hasMore: boolean
  loadingMore: boolean
  onLoadMore: () => void
  editable?: boolean
  savingId?: string | null
  onSaveEntry?: (id: string, payload: TimeActivityUpdatePayload) => Promise<void>
  showApprovalStatus?: boolean
  showEmployee?: boolean
  displayTimezone?: string | null
  reviewable?: boolean
  reviewingId?: string | null
  onApproveEntry?: (id: string) => Promise<void>
  onRejectEntry?: (id: string, reason?: string | null) => Promise<void>
}

/**
 * Spreadsheet-style list of recent time activities with optional load-more pagination.
 * @param props Entries, loading state, and optional administrator edit handlers.
 * @returns Card with a time activity table.
 */
export function TimeActivityEntriesPanel({
  title,
  embedded = false,
  entries,
  loading,
  error,
  hasMore,
  loadingMore,
  onLoadMore,
  editable = false,
  savingId = null,
  onSaveEntry,
  showApprovalStatus = false,
  showEmployee = false,
  displayTimezone = null,
  reviewable = false,
  reviewingId = null,
  onApproveEntry,
  onRejectEntry,
}: TimeActivityEntriesPanelProps) {
  const { t, locale } = useLocale()

  return (
    <section className={embedded ? '' : cardClass}>
      {title ? <h2 className={sectionTitleCompactClass}>{title}</h2> : null}

      {error ? (
        <div className="mt-4">
          <Alert variant="error">{error}</Alert>
        </div>
      ) : null}

      {loading ? (
        <div className="mt-4">
          <LoadingScreen variant="inline" />
        </div>
      ) : entries.length === 0 ? (
        <p className="mt-4 text-sm text-brand-muted">{t('timeActivity.empty')}</p>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="min-w-full divide-y divide-brand-border text-left text-sm">
            <thead className="bg-brand-surface-subtle text-xs font-medium uppercase tracking-wide text-brand-muted-subtle">
              <tr>
                <th className="px-3 py-2">{t('timeActivity.start')}</th>
                <th className="px-3 py-2">{t('timeActivity.end')}</th>
                <th className="px-3 py-2">{t('timeActivity.client')}</th>
                <th className="px-3 py-2">{t('timeActivity.service')}</th>
                <th className="px-3 py-2">{t('timeActivity.description')}</th>
                <th className="px-3 py-2">{t('timeActivity.duration')}</th>
                <th className="px-3 py-2">{t('timeActivity.billable')}</th>
                {showEmployee ? <th className="px-3 py-2">{t('timeActivity.employee')}</th> : null}
                {showApprovalStatus ? <th className="px-3 py-2">{t('timeActivity.status')}</th> : null}
                {editable ? <th className="px-3 py-2">{t('timeActivity.actions')}</th> : null}
                {reviewable ? <th className="px-3 py-2">{t('timeActivity.actions')}</th> : null}
              </tr>
            </thead>
            <tbody className="divide-y divide-brand-border">
              {entries.map((entry) =>
                editable && onSaveEntry ? (
                  <TimeActivityEditableRow
                    key={entry.id}
                    entry={entry}
                    saving={savingId === entry.id}
                    onSave={onSaveEntry}
                  />
                ) : (
                  <tr key={entry.id}>
                    <td className="px-3 py-3 text-brand-primary">
                      {formatEntryDateTime(entry.startTime, locale, displayTimezone) || t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-brand-primary">
                      {formatEntryDateTime(entry.endTime, locale, displayTimezone) || t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-brand-primary">
                      {entry.customerName ?? t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-brand-primary">
                      {entry.serviceName ?? t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-brand-primary">
                      {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-brand-primary">{formatEntryDuration(entry.durationSeconds)}</td>
                    <td className="px-3 py-3 text-brand-primary">
                      {entry.billableLocked
                        ? t('timeActivity.billed')
                        : entry.isBillable
                          ? t('timeActivity.billableYes')
                          : t('timeActivity.billableNo')}
                    </td>
                    {showEmployee ? (
                      <td className="px-3 py-3 text-brand-primary">
                        {entry.employeeName ?? t('timeActivity.noValue')}
                      </td>
                    ) : null}
                    {showApprovalStatus ? (
                      <td className="px-3 py-3 text-brand-primary">
                        {formatApprovalStatus(entry.approvalStatus, t)}
                      </td>
                    ) : null}
                    {reviewable && onApproveEntry && onRejectEntry ? (
                      <td className="px-3 py-3 text-brand-primary">
                        <TimeEntryApprovalActions
                          entryId={entry.id}
                          reviewing={reviewingId === entry.id}
                          onApprove={onApproveEntry}
                          onReject={onRejectEntry}
                        />
                      </td>
                    ) : null}
                  </tr>
                ),
              )}
            </tbody>
          </table>
        </div>
      )}

      {hasMore ? (
        <div className="mt-4">
          <Button type="button" variant="secondary" disabled={loadingMore} onClick={onLoadMore}>
            {loadingMore ? t('timeActivity.loadingMore') : t('timeActivity.loadMore')}
          </Button>
        </div>
      ) : null}
    </section>
  )
}
