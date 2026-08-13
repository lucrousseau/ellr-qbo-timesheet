/**
 * @file Read-only time activity table row with optional draft selection.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import type { UserLocale } from '@ellr/api-client'
import { CheckboxField } from './CheckboxField'
import { formatApprovalStatus, isDraftActionable } from './timeActivityApprovalStatus'
import { TimeActivityDraftActions } from './TimeActivityDraftActions'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { TimeActivityReviewActionsCell } from './TimeActivityReviewActionsCell'

/** Props for {@link TimeActivityReadOnlyRow}. */
export type TimeActivityReadOnlyRowProps = {
  entry: TimeActivityRow
  selectable: boolean
  selected: boolean
  onToggleSelected?: (id: string, selected: boolean) => void
  isRowSelectable?: (entry: TimeActivityRow) => boolean
  showEmployee: boolean
  showApprovalStatus: boolean
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
  locale: UserLocale
  t: (key: string) => string
}

/**
 * Renders one read-only time entry row with optional selection and actions.
 * @param props Row data and handlers.
 * @returns Table row.
 */
export function TimeActivityReadOnlyRow({
  entry,
  selectable,
  selected,
  onToggleSelected,
  isRowSelectable,
  showEmployee,
  showApprovalStatus,
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
  locale,
  t,
}: TimeActivityReadOnlyRowProps) {
  const canSelect =
    selectable &&
    (isRowSelectable?.(entry) ?? isDraftActionable(entry.approvalStatus))

  return (
    <tr className={selected ? 'bg-brand-surface-subtle/60' : undefined}>
      {selectable ? (
        <td className="w-10 px-2 py-2">
          {canSelect ? (
            <CheckboxField
              label={t('timeActivity.selectRow')}
              labelVisuallyHidden
              checked={selected}
              onChange={(checked) => onToggleSelected?.(entry.id, checked)}
            />
          ) : null}
        </td>
      ) : null}
      <td className="whitespace-nowrap px-3 py-2 text-brand-primary">
        {formatEntryDateTime(entry.startTime, locale, displayTimezone) || t('timeActivity.noValue')}
      </td>
      <td className="whitespace-nowrap px-3 py-2 text-brand-primary">
        {formatEntryDateTime(entry.endTime, locale, displayTimezone) || t('timeActivity.noValue')}
      </td>
      <td className="px-3 py-2 text-brand-primary">
        {entry.customerName ?? t('timeActivity.noValue')}
      </td>
      <td className="px-3 py-2 text-brand-primary">
        {entry.serviceName ?? t('timeActivity.noValue')}
      </td>
      <td className="max-w-48 truncate px-3 py-2 text-brand-primary">
        {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
      </td>
      <td className="whitespace-nowrap px-3 py-2 text-brand-primary">
        {formatEntryDuration(entry.durationSeconds)}
      </td>
      <td className="whitespace-nowrap px-3 py-2 text-brand-primary">
        {entry.billableLocked
          ? t('timeActivity.billed')
          : entry.isBillable
            ? t('timeActivity.billableYes')
            : t('timeActivity.billableNo')}
      </td>
      {showEmployee ? (
        <td className="px-3 py-2 text-brand-primary">
          {entry.employeeName ?? t('timeActivity.noValue')}
        </td>
      ) : null}
      {showApprovalStatus ? (
        <td className="whitespace-nowrap px-3 py-2 text-brand-primary">
          {formatApprovalStatus(entry.approvalStatus, t)}
        </td>
      ) : null}
      {reviewable && onApproveEntry && onRejectEntry && onReturnEntryToDraft ? (
        <td className="px-3 py-2 text-brand-primary">
          <TimeActivityReviewActionsCell
            entry={entry}
            reviewing={reviewingId === entry.id}
            onApprove={onApproveEntry}
            onReject={onRejectEntry}
            onReturnToDraft={onReturnEntryToDraft}
            onEditEntry={onEditEntry}
            editLabel={t('admin.editPendingEntry')}
          />
        </td>
      ) : null}
      {draftActions ? (
        <td className="px-3 py-2 text-brand-primary">
          <TimeActivityDraftActions
            entry={entry}
            busy={actionEntryId === entry.id}
            onEditDraft={onEditDraft}
            onDeleteDraft={onDeleteDraft}
          />
        </td>
      ) : null}
    </tr>
  )
}
