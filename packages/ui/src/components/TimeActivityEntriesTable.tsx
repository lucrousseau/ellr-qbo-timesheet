/**
 * @file Spreadsheet table for time activity entries.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { useLocale } from '../i18n/LocaleProvider'
import { formatApprovalStatus } from './timeActivityApprovalStatus'
import { TimeActivityDraftActions } from './TimeActivityDraftActions'
import { TimeActivityEditableRow } from './TimeActivityEditableRow'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'

/** Props for {@link TimeActivityEntriesTable}. */
export type TimeActivityEntriesTableProps = {
  entries: TimeActivityRow[]
  editable: boolean
  savingId: string | null
  onSaveEntry?: (id: string, payload: TimeActivityUpdatePayload) => Promise<void>
  showApprovalStatus: boolean
  showEmployee: boolean
  displayTimezone: string | null
  reviewable: boolean
  reviewingId: string | null
  onApproveEntry?: (id: string) => Promise<void>
  onRejectEntry?: (id: string, reason?: string | null) => Promise<void>
  onReturnEntryToDraft?: (id: string) => Promise<void>
  draftActions: boolean
  actionEntryId: string | null
  onEditDraft?: (entry: TimeActivityRow) => void
  onSubmitDraft?: (id: string) => Promise<void>
  onDeleteDraft?: (id: string) => Promise<void>
}

/**
 * Renders time entries in a horizontal scrollable table.
 * @param props Entries and optional edit or review handlers.
 * @returns Table markup for wide layouts.
 */
export function TimeActivityEntriesTable({
  entries,
  editable,
  savingId,
  onSaveEntry,
  showApprovalStatus,
  showEmployee,
  displayTimezone,
  reviewable,
  reviewingId,
  onApproveEntry,
  onRejectEntry,
  onReturnEntryToDraft,
  draftActions,
  actionEntryId,
  onEditDraft,
  onSubmitDraft,
  onDeleteDraft,
}: TimeActivityEntriesTableProps) {
  const { t, locale } = useLocale()
  const showActions = editable || reviewable || draftActions

  return (
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
            {showActions ? <th className="px-3 py-2">{t('timeActivity.actions')}</th> : null}
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
                <td className="whitespace-nowrap px-3 py-3 text-brand-primary">
                  {formatEntryDateTime(entry.startTime, locale, displayTimezone) || t('timeActivity.noValue')}
                </td>
                <td className="whitespace-nowrap px-3 py-3 text-brand-primary">
                  {formatEntryDateTime(entry.endTime, locale, displayTimezone) || t('timeActivity.noValue')}
                </td>
                <td className="px-3 py-3 text-brand-primary">
                  {entry.customerName ?? t('timeActivity.noValue')}
                </td>
                <td className="px-3 py-3 text-brand-primary">
                  {entry.serviceName ?? t('timeActivity.noValue')}
                </td>
                <td className="max-w-56 px-3 py-3 text-brand-primary">
                  {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
                </td>
                <td className="whitespace-nowrap px-3 py-3 text-brand-primary">
                  {formatEntryDuration(entry.durationSeconds)}
                </td>
                <td className="whitespace-nowrap px-3 py-3 text-brand-primary">
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
                  <td className="whitespace-nowrap px-3 py-3 text-brand-primary">
                    {formatApprovalStatus(entry.approvalStatus, t)}
                  </td>
                ) : null}
                {reviewable && onApproveEntry && onRejectEntry && onReturnEntryToDraft ? (
                  <td className="px-3 py-3 text-brand-primary">
                    <TimeEntryApprovalActions
                      entryId={entry.id}
                      reviewing={reviewingId === entry.id}
                      onApprove={onApproveEntry}
                      onReject={onRejectEntry}
                      onReturnToDraft={onReturnEntryToDraft}
                    />
                  </td>
                ) : null}
                {draftActions ? (
                  <td className="px-3 py-3 text-brand-primary">
                    <TimeActivityDraftActions
                      entry={entry}
                      busy={actionEntryId === entry.id}
                      onEditDraft={onEditDraft}
                      onSubmitDraft={onSubmitDraft}
                      onDeleteDraft={onDeleteDraft}
                    />
                  </td>
                ) : null}
              </tr>
            ),
          )}
        </tbody>
      </table>
    </div>
  )
}
