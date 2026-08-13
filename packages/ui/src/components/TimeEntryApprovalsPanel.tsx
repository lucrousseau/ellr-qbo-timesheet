/**
 * @file Shared pending time entry approvals panel (admin and supervisor).
 */

import { useState } from 'react'
import type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'
import { cardClass, sectionHelpClass, sectionTitleCompactClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { useTimeEntryApprovals } from '../hooks/useTimeEntryApprovals'
import { useTimeEntryReviewFilters } from '../hooks/useTimeEntryReviewFilters'
import { TimeActivityEntriesPanel } from './TimeActivityEntriesPanel'
import { TimeEntryApprovalEditDialog } from './TimeEntryApprovalEditDialog'
import { TimeEntryApprovalsFilters } from './TimeEntryApprovalsFilters'

type TimeEntryApprovalsPanelProps = {
  enabled: boolean
  admin?: boolean
  title: string
  help: string
  emptyMessage: string
  messages?: Parameters<typeof useTimeEntryApprovals>[0]['messages']
  displayTimezone?: string | null
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Lists pending time entries in the shared table with approve, reject, return, and edit.
 * @param props Enable flag, copy, route mode, and flash callbacks.
 * @returns Approval review panel.
 */
export function TimeEntryApprovalsPanel({
  enabled,
  admin = true,
  title,
  help,
  emptyMessage,
  messages,
  displayTimezone = null,
  onSuccess,
  onError,
}: TimeEntryApprovalsPanelProps) {
  const { t } = useLocale()
  const approvals = useTimeEntryApprovals({
    enabled,
    admin,
    messages,
    onSuccess,
    onError,
  })
  const [editingEntry, setEditingEntry] = useState<TimeActivityRow | null>(null)
  const reviewFilters = useTimeEntryReviewFilters(approvals.entries)

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleCompactClass}>{title}</h2>
      <p className={sectionHelpClass}>{help}</p>

      <div className="mt-4 space-y-4">
        {!approvals.loading && approvals.entries.length > 0 ? (
          <TimeEntryApprovalsFilters
            filters={reviewFilters.filters}
            employeeOptions={reviewFilters.employeeOptions}
            clientOptions={reviewFilters.clientOptions}
            onChange={reviewFilters.setFilters}
          />
        ) : null}

        <TimeActivityEntriesPanel
          embedded
          title=""
          entries={reviewFilters.filteredEntries}
          loading={approvals.loading}
          error={approvals.error}
          hasMore={approvals.hasMore}
          loadingMore={approvals.loadingMore}
          onLoadMore={approvals.loadMore}
          layout="table"
          showEmployee
          showApprovalStatus
          displayTimezone={displayTimezone}
          reviewable
          reviewingId={approvals.reviewingId}
          onApproveEntry={approvals.approveEntry}
          onRejectEntry={approvals.rejectEntry}
          onReturnEntryToDraft={approvals.returnEntryToDraft}
          onEditEntry={setEditingEntry}
          emptyMessage={
            reviewFilters.filtersActive && approvals.entries.length > 0
              ? t('timeActivity.filterEmpty')
              : emptyMessage
          }
        />
      </div>

      <TimeEntryApprovalEditDialog
        entry={editingEntry}
        saving={editingEntry !== null && approvals.reviewingId === editingEntry.id}
        onClose={() => setEditingEntry(null)}
        onSave={async (id: string, payload: TimeEntryUpdatePayload) => {
          await approvals.updateEntry(id, payload)
        }}
      />
    </section>
  )
}
