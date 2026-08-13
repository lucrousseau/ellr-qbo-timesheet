/**
 * @file Draft time entry action buttons shared by table and stack layouts.
 */

import type { TimeActivityRow } from '@ellr/api-client'
import { Button } from './Button'
import { useLocale } from '../i18n/LocaleProvider'
import { isDraftActionable } from './timeActivityApprovalStatus'

/** Props for {@link TimeActivityDraftActions}. */
export type TimeActivityDraftActionsProps = {
  entry: TimeActivityRow
  busy: boolean
  onEditDraft?: (entry: TimeActivityRow) => void
  onSubmitDraft?: (id: string) => Promise<void>
  onDeleteDraft?: (id: string) => Promise<void>
}

/**
 * Edit, submit, and delete controls for employee draft time entries.
 * @param props Entry and draft action handlers.
 * @returns Action button row, or a placeholder when the entry is not actionable.
 */
export function TimeActivityDraftActions({
  entry,
  busy,
  onEditDraft,
  onSubmitDraft,
  onDeleteDraft,
}: TimeActivityDraftActionsProps) {
  const { t } = useLocale()

  if (!isDraftActionable(entry.approvalStatus)) {
    return <span className="text-brand-muted">{t('timeActivity.noValue')}</span>
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Button
        type="button"
        variant="secondary"
        size="compact"
        disabled={busy}
        onClick={() => onEditDraft?.(entry)}
      >
        {t('timesheet.editDraftEntry')}
      </Button>
      <Button
        type="button"
        size="compact"
        disabled={busy}
        onClick={() => void onSubmitDraft?.(entry.id)}
      >
        {busy ? t('timesheet.submittingForApproval') : t('timesheet.submitForApproval')}
      </Button>
      <Button
        type="button"
        variant="danger"
        size="compact"
        disabled={busy}
        onClick={() => void onDeleteDraft?.(entry.id)}
      >
        {busy ? t('timesheet.deletingDraftEntry') : t('timesheet.deleteDraftEntry')}
      </Button>
    </div>
  )
}
