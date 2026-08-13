/**
 * @file Card list for reviewing and approving pending time entries.
 */

import type { TimeActivityRow, TimeEntryUpdatePayload } from '@ellr/api-client'
import { useState } from 'react'
import { Button } from './Button'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { TimeEntryApprovalActions } from './TimeEntryApprovalActions'
import { TimeEntryApprovalEditor } from './TimeEntryApprovalEditor'
import { useLocale } from '../i18n/LocaleProvider'

type TimeEntryApprovalListProps = {
  entries: TimeActivityRow[]
  reviewingId: string | null
  displayTimezone?: string | null
  onApprove: (id: string) => Promise<void>
  onReject: (id: string, reason?: string | null) => Promise<void>
  onReturnToDraft: (id: string) => Promise<void>
  onUpdateEntry?: (id: string, payload: TimeEntryUpdatePayload) => Promise<void>
}

/**
 * Renders pending time entries as review cards with visible approve, reject, and return-to-draft actions.
 * @param props Pending entries and review handlers.
 * @returns Approval card list.
 */
export function TimeEntryApprovalList({
  entries,
  reviewingId,
  displayTimezone = null,
  onApprove,
  onReject,
  onReturnToDraft,
  onUpdateEntry,
}: TimeEntryApprovalListProps) {
  const { t, locale } = useLocale()
  const [editingId, setEditingId] = useState<string | null>(null)

  return (
    <ul className="space-y-4">
      {entries.map((entry) => (
        <li
          key={entry.id}
          className="rounded-lg border border-brand-border bg-white p-4 shadow-sm"
        >
          <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div className="min-w-0 space-y-2 text-sm text-brand-primary">
              <p className="text-base font-medium text-brand-primary">
                {entry.employeeName ?? t('timeActivity.noValue')}
              </p>
              <p>
                {formatEntryDateTime(entry.startTime, locale, displayTimezone) || t('timeActivity.noValue')}
                {' '}
                {t('timeActivity.rangeSeparator')}
                {' '}
                {formatEntryDateTime(entry.endTime, locale, displayTimezone) || t('timeActivity.noValue')}
                {' '}
                ({formatEntryDuration(entry.durationSeconds)})
              </p>
              <p>
                {entry.customerName ?? t('timeActivity.noValue')}
                {' '}
                ·
                {' '}
                {entry.serviceName ?? t('timeActivity.noValue')}
              </p>
              {entry.description?.trim() ? <p>{entry.description}</p> : null}
              <p className="text-brand-muted-subtle">
                {entry.isBillable ? t('timeActivity.billableYes') : t('timeActivity.billableNo')}
              </p>
              {onUpdateEntry && editingId !== entry.id ? (
                <Button
                  type="button"
                  variant="link"
                  disabled={reviewingId === entry.id}
                  onClick={() => setEditingId(entry.id)}
                >
                  {t('admin.editPendingEntry')}
                </Button>
              ) : null}
              {onUpdateEntry && editingId === entry.id ? (
                <TimeEntryApprovalEditor
                  entry={entry}
                  saving={reviewingId === entry.id}
                  onCancel={() => setEditingId(null)}
                  onSave={async (id, payload) => {
                    await onUpdateEntry(id, payload)
                    setEditingId(null)
                  }}
                />
              ) : null}
            </div>

            <TimeEntryApprovalActions
              entryId={entry.id}
              reviewing={reviewingId === entry.id}
              onApprove={onApprove}
              onReject={onReject}
              onReturnToDraft={onReturnToDraft}
            />
          </div>
        </li>
      ))}
    </ul>
  )
}
