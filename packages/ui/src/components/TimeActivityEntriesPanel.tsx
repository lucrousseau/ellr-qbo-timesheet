/**
 * @file Read-only and editable time activity lists with pagination.
 */

import type { TimeActivityRow, TimeActivityUpdatePayload } from '@ellr/api-client'
import { Alert } from './Alert'
import { Button } from './Button'
import { LoadingScreen } from './LoadingScreen'
import { cardClass, sectionTitleCompactClass } from '../styles/tokens'
import { useLocale } from '../i18n/LocaleProvider'
import { TimeActivityEntriesCards } from './TimeActivityEntriesCards'
import { TimeActivityEntriesTable } from './TimeActivityEntriesTable'

/** Props for {@link TimeActivityEntriesPanel}. */
export type TimeActivityEntriesPanelProps = {
  title?: string
  embedded?: boolean
  /** `cards` stacks entries for narrow shells; `table` keeps the spreadsheet layout. */
  layout?: 'table' | 'cards'
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
  onReturnEntryToDraft?: (id: string) => Promise<void>
  draftActions?: boolean
  actionEntryId?: string | null
  onEditDraft?: (entry: TimeActivityRow) => void
  onSubmitDraft?: (id: string) => Promise<void>
  onDeleteDraft?: (id: string) => Promise<void>
}

/**
 * List of recent time activities with optional load-more pagination.
 * @param props Entries, loading state, and optional edit or draft handlers.
 * @returns Card with a table or stacked entry list.
 */
export function TimeActivityEntriesPanel({
  title,
  embedded = false,
  layout = 'table',
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
  onReturnEntryToDraft,
  draftActions = false,
  actionEntryId = null,
  onEditDraft,
  onSubmitDraft,
  onDeleteDraft,
}: TimeActivityEntriesPanelProps) {
  const { t } = useLocale()
  const useCards = layout === 'cards' && !editable && !reviewable

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
      ) : useCards ? (
        <TimeActivityEntriesCards
          entries={entries}
          showApprovalStatus={showApprovalStatus}
          displayTimezone={displayTimezone}
          draftActions={draftActions}
          actionEntryId={actionEntryId}
          onEditDraft={onEditDraft}
          onSubmitDraft={onSubmitDraft}
          onDeleteDraft={onDeleteDraft}
        />
      ) : (
        <TimeActivityEntriesTable
          entries={entries}
          editable={editable}
          savingId={savingId}
          onSaveEntry={onSaveEntry}
          showApprovalStatus={showApprovalStatus}
          showEmployee={showEmployee}
          displayTimezone={displayTimezone}
          reviewable={reviewable}
          reviewingId={reviewingId}
          onApproveEntry={onApproveEntry}
          onRejectEntry={onRejectEntry}
          onReturnEntryToDraft={onReturnEntryToDraft}
          draftActions={draftActions}
          actionEntryId={actionEntryId}
          onEditDraft={onEditDraft}
          onSubmitDraft={onSubmitDraft}
          onDeleteDraft={onDeleteDraft}
        />
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
