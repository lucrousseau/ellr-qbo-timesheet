/**
 * @file Dialog that lists local time entries aggregated into one QuickBooks activity.
 */

import { fetchTimeEntrySyncGroup, type TimeEntrySyncGroup } from '@ellr/api-client'
import { useEffect, useState } from 'react'
import { AppDialog } from './AppDialog'
import { Alert } from './Alert'
import { Button } from './Button'
import { LoadingScreen } from './LoadingScreen'
import { useLocale } from '../i18n/LocaleProvider'
import { formatEntryDateTime, formatEntryDuration } from './timeActivityDisplay'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'

/** Props for {@link TimeEntrySyncGroupDialog}. */
export type TimeEntrySyncGroupDialogProps = {
  publicId: string | null
  onClose: () => void
  displayTimezone?: string | null
}

/**
 * Accountant-facing detail view for a grouped QuickBooks sync.
 * @param props Open public id, close handler, and optional display timezone.
 * @returns Modal with member entries for the sync group.
 */
export function TimeEntrySyncGroupDialog({
  publicId,
  onClose,
  displayTimezone = null,
}: TimeEntrySyncGroupDialogProps) {
  const { t, locale } = useLocale()
  const [group, setGroup] = useState<TimeEntrySyncGroup | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!publicId) {
      setGroup(null)
      setError(null)
      setLoading(false)
      return
    }

    let cancelled = false
    setLoading(true)
    setError(null)

    void fetchTimeEntrySyncGroup(publicId)
      .then((payload) => {
        if (!cancelled) {
          setGroup(payload)
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setGroup(null)
          setError(getApiErrorMessage(cause, t('timeActivity.syncGroupLoadFailed'), locale))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [publicId, t, locale])

  return (
    <AppDialog
      open={publicId !== null}
      title={t('timeActivity.syncGroupTitle')}
      onClose={onClose}
      footer={
        <Button type="button" variant="secondary" onClick={onClose}>
          {t('common.cancel')}
        </Button>
      }
    >
      {loading ? <LoadingScreen variant="inline" /> : null}
      {error ? <Alert variant="error">{error}</Alert> : null}
      {!loading && !error && group ? (
        <div className="space-y-4 text-sm text-brand-primary">
          <p className="text-brand-muted">{t('timeActivity.syncGroupHelp')}</p>
          <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.employee')}
              </dt>
              <dd>{group.employee_name ?? t('timeActivity.noValue')}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.duration')}
              </dt>
              <dd>{formatEntryDuration(group.total_duration_seconds)}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.syncGroupQboId')}
              </dt>
              <dd>{group.qbo_id ?? t('timeActivity.noValue')}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase tracking-wide text-brand-muted-subtle">
                {t('timeActivity.syncGroupMembers')}
              </dt>
              <dd>{group.member_count}</dd>
            </div>
          </dl>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-brand-border text-left">
              <thead className="bg-brand-surface-subtle text-xs font-medium uppercase tracking-wide text-brand-muted-subtle">
                <tr>
                  <th className="px-3 py-2">{t('timeActivity.start')}</th>
                  <th className="px-3 py-2">{t('timeActivity.end')}</th>
                  <th className="px-3 py-2">{t('timeActivity.description')}</th>
                  <th className="px-3 py-2">{t('timeActivity.duration')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-brand-border">
                {group.entries.map((entry) => (
                  <tr key={entry.list_id}>
                    <td className="px-3 py-2">
                      {formatEntryDateTime(entry.start_time, locale, displayTimezone) ||
                        t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-2">
                      {formatEntryDateTime(entry.end_time, locale, displayTimezone) ||
                        t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-2">
                      {entry.description?.trim() ? entry.description : t('timeActivity.noValue')}
                    </td>
                    <td className="px-3 py-2">{formatEntryDuration(entry.duration_seconds)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </AppDialog>
  )
}
