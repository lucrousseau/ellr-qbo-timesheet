/**
 * @file Recent time entries with draft submit and edit actions.
 */

import { Button, TimeActivityEntriesPanel, useLocale } from '@ellr/ui'
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
  submittingAll: boolean
  onEditDraft: (entry: TimeActivityRow) => void
  onSubmitDraft: (id: string) => Promise<void>
  onDeleteDraft: (id: string) => Promise<void>
  onSubmitAllDrafts: () => void | Promise<void>
  onCloseEditor: () => void
  onSaveDraft: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Recent entries list with bulk submit and draft edit dialog.
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
  submittingAll,
  onEditDraft,
  onSubmitDraft,
  onDeleteDraft,
  onSubmitAllDrafts,
  onCloseEditor,
  onSaveDraft,
}: RecentDraftEntriesSectionProps) {
  const { t } = useLocale()
  const hasSubmittableDrafts = entries.some(
    (entry) => entry.approvalStatus === 'draft' || entry.approvalStatus === 'rejected',
  )

  return (
    <div className="space-y-3">
      <p className="text-sm text-brand-muted">{t('timesheet.draftEntriesHelp')}</p>
      {hasSubmittableDrafts ? (
        <Button
          type="button"
          variant="secondary"
          disabled={submittingAll}
          onClick={() => void onSubmitAllDrafts()}
        >
          {submittingAll ? t('timesheet.submittingAllDrafts') : t('timesheet.submitAllDrafts')}
        </Button>
      ) : null}
      <TimeActivityEntriesPanel
        title={t('timesheet.recentEntriesTitle')}
        entries={entries}
        loading={loading}
        error={error}
        hasMore={hasMore}
        loadingMore={loadingMore}
        onLoadMore={onLoadMore}
        layout="cards"
        showApprovalStatus
        displayTimezone={displayTimezone}
        draftActions
        actionEntryId={actionEntryId}
        onEditDraft={onEditDraft}
        onSubmitDraft={onSubmitDraft}
        onDeleteDraft={onDeleteDraft}
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
