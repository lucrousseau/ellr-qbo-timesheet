/**
 * @file Draft time entry icon actions shared by table and stack layouts.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import { Button } from './Button'
import { useLocale } from '../i18n/LocaleProvider'
import { isDraftActionable } from './timeActivityApprovalStatus'
import { PencilIcon } from './icons/PencilIcon'
import { TrashIcon } from './icons/TrashIcon'

/** Props for {@link TimeActivityDraftActions}. */
export type TimeActivityDraftActionsProps = {
  entry: TimeActivityRow
  busy: boolean
  onEditDraft?: (entry: TimeActivityRow) => void
  onDeleteDraft?: (id: string) => Promise<void>
}

/**
 * Compact edit and delete icon controls for employee draft time entries.
 * @param props Entry and draft action handlers.
 * @returns Icon action row, or a placeholder when the entry is not actionable.
 */
export function TimeActivityDraftActions({
  entry,
  busy,
  onEditDraft,
  onDeleteDraft,
}: TimeActivityDraftActionsProps) {
  const { t } = useLocale()

  if (!isDraftActionable(entry.approvalStatus)) {
    return <span className="text-brand-muted">{t('timeActivity.noValue')}</span>
  }

  return (
    <div className="flex items-center gap-0.5">
      <Button
        type="button"
        size="icon"
        disabled={busy}
        aria-label={t('timesheet.editDraftEntry')}
        onClick={() => onEditDraft?.(entry)}
      >
        <PencilIcon />
      </Button>
      <Button
        type="button"
        variant="danger"
        size="icon"
        disabled={busy}
        aria-label={busy ? t('timesheet.deletingDraftEntry') : t('timesheet.deleteDraftEntry')}
        onClick={() => void onDeleteDraft?.(entry.id)}
      >
        <TrashIcon />
      </Button>
    </div>
  )
}
