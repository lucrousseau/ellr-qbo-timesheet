/**
 * @file Review action cell for pending time entry table rows.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import { Button } from './Button'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'
import { PencilIcon } from './icons/PencilIcon'

type TimeActivityReviewActionsCellProps = {
  entry: TimeActivityRow
  reviewing: boolean
  onApprove: (id: string) => Promise<void>
  onReject: (id: string, reason?: string | null) => Promise<void>
  onReturnToDraft: (id: string) => Promise<void>
  onEditEntry?: (entry: TimeActivityRow) => void
  editLabel: string
}

/**
 * Pencil plus approve/reject/return icons for one pending row.
 * @param props Entry, busy state, and review handlers.
 * @returns Actions table cell contents.
 */
export function TimeActivityReviewActionsCell({
  entry,
  reviewing,
  onApprove,
  onReject,
  onReturnToDraft,
  onEditEntry,
  editLabel,
}: TimeActivityReviewActionsCellProps) {
  return (
    <div className="flex items-start gap-0.5">
      {onEditEntry ? (
        <Button
          type="button"
          size="icon"
          disabled={reviewing}
          aria-label={editLabel}
          onClick={() => onEditEntry(entry)}
        >
          <PencilIcon />
        </Button>
      ) : null}
      <TimeEntryApprovalActions
        entryId={entry.id}
        reviewing={reviewing}
        onApprove={onApprove}
        onReject={onReject}
        onReturnToDraft={onReturnToDraft}
      />
    </div>
  )
}
