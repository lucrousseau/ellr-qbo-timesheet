/**
 * @file Read-only and editable time activity tables with pagination.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { Alert } from './Alert'
import { Button } from './Button'
import { LoadingScreen } from './LoadingScreen'
import { cardClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { TimeActivityEditableRow } from './TimeActivityEditableRow'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'

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
}: TimeActivityEntriesPanelProps) {
  const { t, locale } = useLocale()

  return (
    <section className={embedded ? '' : cardClass}>
      {title ? <h2 className="text-lg font-medium text-slate-900">{title}</h2> : null}

      {error ? (
        <div className="mt-4">
          <Alert variant="error">{error}</Alert>
        </div>
      ) : null}

      {loading ? (
        <div className="mt-4">
          <LoadingScreen />
        </div>
      ) : entries.length === 0 ? (
        <p className="mt-4 text-sm text-slate-600">{t('timeActivity.empty')}</p>
      ) : (
        <div className="mt-4 overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
              <tr>
                <th className="px-3 py-2">{t('timeActivity.start')}</th>
                <th className="px-3 py-2">{t('timeActivity.end')}</th>
                <th className="px-3 py-2">{t('timeActivity.client')}</th>
                <th className="px-3 py-2">{t('timeActivity.service')}</th>
                <th className="px-3 py-2">{t('timeActivity.description')}</th>
                <th className="px-3 py-2">{t('timeActivity.duration')}</th>
                <th className="px-3 py-2">{t('timeActivity.billable')}</th>
                {editable ? <th className="px-3 py-2">{t('timeActivity.actions')}</th> : null}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
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
                    <td className="px-3 py-3 text-slate-700">
                      {formatEntryDateTime(entry.startTime, locale) || t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-slate-700">
                      {formatEntryDateTime(entry.endTime, locale) || t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-slate-700">
                      {entry.customerName ?? t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-slate-700">
                      {entry.serviceName ?? t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-slate-700">
                      {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-3 text-slate-700">{formatEntryDuration(entry.durationSeconds)}</td>
                    <td className="px-3 py-3 text-slate-700">
                      {entry.billableLocked
                        ? t('timeActivity.billed')
                        : entry.isBillable
                          ? t('timeActivity.billableYes')
                          : t('timeActivity.billableNo')}
                    </td>
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
