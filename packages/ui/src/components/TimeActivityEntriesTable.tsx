/**
 * @file Spreadsheet table for time activity entries.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { useLocale } from '../i18n/LocaleProvider'
import { CheckboxField } from './CheckboxField'
import { isDraftActionable } from './timeActivityApprovalStatus'
import { TimeActivityEditableRow } from './TimeActivityEditableRow'
import { TimeActivityReadOnlyRow } from './TimeActivityReadOnlyRow'

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
  onEditEntry?: (entry: TimeActivityRow) => void
  draftActions: boolean
  actionEntryId: string | null
  onEditDraft?: (entry: TimeActivityRow) => void
  onDeleteDraft?: (id: string) => Promise<void>
  selectable?: boolean
  isRowSelectable?: (entry: TimeActivityRow) => boolean
  selectedIds?: readonly string[]
  onToggleSelected?: (id: string, selected: boolean) => void
  onToggleSelectAll?: (selected: boolean) => void
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
  onEditEntry,
  draftActions,
  actionEntryId,
  onEditDraft,
  onDeleteDraft,
  selectable = false,
  isRowSelectable,
  selectedIds = [],
  onToggleSelected,
  onToggleSelectAll,
}: TimeActivityEntriesTableProps) {
  const { t, locale } = useLocale()
  const showActions = editable || reviewable || draftActions
  const rowIsSelectable =
    isRowSelectable ?? ((entry: TimeActivityRow) => isDraftActionable(entry.approvalStatus))
  const selectableIds = selectable
    ? entries.filter((entry) => rowIsSelectable(entry)).map((entry) => entry.id)
    : []
  const selectedSet = new Set(selectedIds)
  const allSelectableSelected =
    selectableIds.length > 0 && selectableIds.every((id) => selectedSet.has(id))

  return (
    <div className="mt-4 overflow-x-auto">
      <table className="min-w-full divide-y divide-brand-border text-left text-sm">
        <thead className="bg-brand-surface-subtle text-xs font-medium uppercase tracking-wide text-brand-muted-subtle">
          <tr>
            {selectable ? (
              <th className="w-10 px-2 py-2">
                <CheckboxField
                  label={t('timeActivity.selectAllRows')}
                  labelVisuallyHidden
                  checked={allSelectableSelected}
                  disabled={selectableIds.length === 0}
                  onChange={(checked) => onToggleSelectAll?.(checked)}
                />
              </th>
            ) : null}
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
              <TimeActivityReadOnlyRow
                key={entry.id}
                entry={entry}
                selectable={selectable}
                selected={selectedSet.has(entry.id)}
                onToggleSelected={onToggleSelected}
                showEmployee={showEmployee}
                showApprovalStatus={showApprovalStatus}
                displayTimezone={displayTimezone}
                reviewable={reviewable}
                reviewingId={reviewingId}
                onApproveEntry={onApproveEntry}
                onRejectEntry={onRejectEntry}
                onReturnEntryToDraft={onReturnEntryToDraft}
                onEditEntry={onEditEntry}
                draftActions={draftActions}
                actionEntryId={actionEntryId}
                onEditDraft={onEditDraft}
                onDeleteDraft={onDeleteDraft}
                isRowSelectable={rowIsSelectable}
                locale={locale}
                t={t}
              />
            ),
          )}
        </tbody>
      </table>
    </div>
  )
}
