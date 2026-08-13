/**
 * @file Approve, reject, and return-to-draft icon actions for pending time entries.
 */

import { useState } from 'react'
import { Button } from './Button'
import { TextAreaField } from './TextAreaField'
import { useLocale } from '../i18n/LocaleProvider'
import { ArrowUturnLeftIcon } from './icons/ArrowUturnLeftIcon'
import { CheckIcon } from './icons/CheckIcon'
import { XMarkIcon } from './icons/XMarkIcon'

type TimeEntryApprovalActionsProps = {
  entryId: string
  reviewing: boolean
  onApprove: (id: string) => Promise<void>
  onReject: (id: string, reason?: string | null) => Promise<void>
  onReturnToDraft: (id: string) => Promise<void>
}

/**
 * Compact review controls for a pending time entry row.
 * @param props Entry id, pending state, and review handlers.
 * @returns Icon actions for supervisor review, or the reject reason form.
 */
export function TimeEntryApprovalActions({
  entryId,
  reviewing,
  onApprove,
  onReject,
  onReturnToDraft,
}: TimeEntryApprovalActionsProps) {
  const { t } = useLocale()
  const [showRejectForm, setShowRejectForm] = useState(false)
  const [reason, setReason] = useState('')

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
    <div className="flex items-center gap-0.5">
      <Button
        type="button"
        size="icon"
        disabled={reviewing}
        aria-label={reviewing ? t('admin.approvingEntry') : t('admin.approveEntry')}
        onClick={() => void onApprove(entryId)}
      >
        <CheckIcon />
      </Button>
      <Button
        type="button"
        variant="secondary"
        size="icon"
        disabled={reviewing}
        aria-label={t('admin.rejectEntry')}
        onClick={() => setShowRejectForm(true)}
      >
        <XMarkIcon />
      </Button>
      <Button
        type="button"
        variant="secondary"
        size="icon"
        disabled={reviewing}
        aria-label={reviewing ? t('admin.returningToDraftEntry') : t('admin.returnToDraftEntry')}
        onClick={() => void onReturnToDraft(entryId)}
      >
        <ArrowUturnLeftIcon />
      </Button>
    </div>
  )
}
