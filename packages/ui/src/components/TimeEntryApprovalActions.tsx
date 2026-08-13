/**
 * @file Approve and reject actions for pending time entries.
 */

import { useState } from 'react'
import { Button } from './Button'
import { CheckboxField } from './CheckboxField'
import { TextAreaField } from './TextAreaField'
import { useLocale } from '../i18n/LocaleProvider'

/** Options chosen by the reviewer when approving a pending time entry. */
export type ApproveTimeEntryOptions = {
  /** When true, matching opted-in siblings coalesce into one QuickBooks activity. */
  groupForQbo?: boolean
}

type TimeEntryApprovalActionsProps = {
  entryId: string
  reviewing: boolean
  onApprove: (id: string, options?: ApproveTimeEntryOptions) => Promise<void>
  onReject: (id: string, reason?: string | null) => Promise<void>
}

/**
 * Inline approve and reject controls for a pending time entry row.
 * @param props Entry id, pending state, and review handlers.
 * @returns Action buttons for supervisor review.
 */
export function TimeEntryApprovalActions({
  entryId,
  reviewing,
  onApprove,
  onReject,
}: TimeEntryApprovalActionsProps) {
  const { t } = useLocale()
  const [showRejectForm, setShowRejectForm] = useState(false)
  const [reason, setReason] = useState('')
  const [groupForQbo, setGroupForQbo] = useState(false)

  if (showRejectForm) {
    return (
      <div className="min-w-64 space-y-2">
        <TextAreaField
          id={`reject-reason-${entryId}`}
          label={t('admin.rejectionReasonLabel')}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          rows={2}
        />
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="danger"
            size="compact"
            disabled={reviewing}
            onClick={() => void onReject(entryId, reason.trim() === '' ? null : reason)}
          >
            {reviewing ? t('admin.rejectingEntry') : t('admin.rejectEntry')}
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="compact"
            disabled={reviewing}
            onClick={() => {
              setShowRejectForm(false)
              setReason('')
            }}
          >
            {t('common.cancel')}
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="min-w-64 space-y-3">
      <CheckboxField
        label={t('admin.groupForQboLabel')}
        checked={groupForQbo}
        disabled={reviewing}
        onChange={setGroupForQbo}
      />
      <p className="text-xs text-brand-muted-subtle">{t('admin.groupForQboHelp')}</p>
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          variant="primary"
          size="compact"
          disabled={reviewing}
          onClick={() => void onApprove(entryId, { groupForQbo })}
        >
          {reviewing ? t('admin.approvingEntry') : t('admin.approveEntry')}
        </Button>
        <Button
          type="button"
          variant="secondary"
          size="compact"
          disabled={reviewing}
          onClick={() => setShowRejectForm(true)}
        >
          {t('admin.rejectEntry')}
        </Button>
      </div>
    </div>
  )
}
