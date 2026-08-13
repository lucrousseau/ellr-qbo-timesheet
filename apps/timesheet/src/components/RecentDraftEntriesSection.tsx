/**
 * @file Recent time entries with status filter, row selection, and draft actions.
 */

import { useEffect, useMemo, useState } from 'react'
import {
  Button,
  DEFAULT_TIME_ENTRY_STATUS_FILTER,
  filterTimeEntriesByStatus,
  isDraftActionable,
  TimeActivityEntriesPanel,
  TimeEntryStatusFilter,
  useLocale,
  type TimeEntryStatusFilterValue,
} from '@ellr/ui'
import type { TimeActivityRow, TimeEntryUpdatePayload } from '../hooks/useDraftTimeEntryActions'
import { DraftTimeEntryEditDialog } from './DraftTimeEntryEditDialog'

type RecentDraftEntriesSectionProps = {
  entries: TimeActivityRow[]
  loading: boolean
  error: string | null
  hasMore: boolean
  loadingMore: boolean
  onLoadMore: () => void
  displayTimezone: string
  actionEntryId: string | null
  editingEntry: TimeActivityRow | null
  submittingSelected: boolean
  onEditDraft: (entry: TimeActivityRow) => void
  onDeleteDraft: (id: string) => Promise<void>
  onSubmitSelectedDrafts: (ids: string[]) => void | Promise<void>
  onCloseEditor: () => void
  onSaveDraft: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Recent entries table with status filter, multi-select submit, and draft edit dialog.
 * @param props Entries state and draft action handlers.
 * @returns Recent entries section.
 */
export function RecentDraftEntriesSection({
  entries,
  loading,
  error,
  hasMore,
  loadingMore,
  onLoadMore,
  displayTimezone,
  actionEntryId,
  editingEntry,
  submittingSelected,
  onEditDraft,
  onDeleteDraft,
  onSubmitSelectedDrafts,
  onCloseEditor,
  onSaveDraft,
}: RecentDraftEntriesSectionProps) {
  const { t } = useLocale()
  const [statusFilter, setStatusFilter] = useState<TimeEntryStatusFilterValue>(
    DEFAULT_TIME_ENTRY_STATUS_FILTER,
  )
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const filteredEntries = filterTimeEntriesByStatus(entries, statusFilter)
  const selectableIds = useMemo(
    () =>
      filteredEntries
        .filter((entry) => isDraftActionable(entry.approvalStatus))
        .map((entry) => entry.id),
    [filteredEntries],
  )
  const selectableIdsKey = selectableIds.join(',')

  useEffect(() => {
    const allowed = new Set(selectableIdsKey === '' ? [] : selectableIdsKey.split(','))
    setSelectedIds((current) => {
      const next = current.filter((id) => allowed.has(id))
      return next.length === current.length ? current : next
    })
  }, [selectableIdsKey])

  const selectedCount = selectedIds.length

  return (
    <div className="space-y-3">
      <p className="text-sm text-brand-muted">{t('timesheet.draftEntriesHelp')}</p>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <TimeEntryStatusFilter value={statusFilter} onChange={setStatusFilter} />
        <Button
          type="button"
          variant="secondary"
          size="compact"
          disabled={selectedCount === 0 || submittingSelected}
          onClick={() => void onSubmitSelectedDrafts(selectedIds)}
        >
          {submittingSelected
            ? t('timesheet.submittingSelectedDrafts')
            : t('timesheet.submitSelectedDrafts', { count: String(selectedCount) })}
        </Button>
      </div>
      <TimeActivityEntriesPanel
        title={t('timesheet.recentEntriesTitle')}
        entries={filteredEntries}
        loading={loading}
        error={error}
        hasMore={hasMore}
        loadingMore={loadingMore}
        onLoadMore={onLoadMore}
        layout="table"
        showApprovalStatus
        displayTimezone={displayTimezone}
        draftActions
        actionEntryId={actionEntryId}
        onEditDraft={onEditDraft}
        onDeleteDraft={onDeleteDraft}
        emptyMessage={entries.length > 0 ? t('timeActivity.filterEmpty') : undefined}
        selectable
        selectedIds={selectedIds}
        onToggleSelected={(id, selected) => {
          setSelectedIds((current) =>
            selected ? [...current, id] : current.filter((value) => value !== id),
          )
        }}
        onToggleSelectAll={(selected) => {
          setSelectedIds(selected ? selectableIds : [])
        }}
      />
      <DraftTimeEntryEditDialog
        entry={editingEntry}
        saving={actionEntryId === editingEntry?.id}
        onClose={onCloseEditor}
        onSave={onSaveDraft}
      />
    </div>
  )
}
