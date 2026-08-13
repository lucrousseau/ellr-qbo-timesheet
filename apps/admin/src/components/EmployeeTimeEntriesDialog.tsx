/**
 * @file Dialog showing read-only QuickBooks-synced time entries for an employee.
 */

import { AppDialog, TimeActivityEntriesPanel, useLocale } from '@ellr/ui'
import type { User } from '../hooks/useTimesheetProvisioning'
import { useAdminUserTimeActivities } from '../hooks/useAdminUserTimeActivities'

type EmployeeTimeEntriesDialogProps = {
  user: User | null
  onClose: () => void
}

/**
 * Administrator dialog to view an employee's QuickBooks-synced time entries.
 * @param props Target user and close handler.
 * @returns Employee time entries dialog or null when closed.
 */
export function EmployeeTimeEntriesDialog({
  user,
  onClose,
}: EmployeeTimeEntriesDialogProps) {
  const { t } = useLocale()
  const activities = useAdminUserTimeActivities({
    userId: user?.id ?? null,
    enabled: user !== null,
  })
  const showEmptyHint = !activities.loading && !activities.error && activities.entries.length === 0

  return (
    <AppDialog
      open={user !== null}
      title={t('admin.employeeTimeEntriesTitle', { name: user?.name ?? '' })}
      onClose={onClose}
      panelClassName="w-full max-w-6xl rounded-xl border border-brand-border bg-white p-6 shadow-xl"
    >
      <p className="text-sm text-brand-muted">{t('admin.employeeTimeEntriesHelp')}</p>

      <div className="mt-4">
        {showEmptyHint ? (
          <p className="text-sm text-brand-muted">{t('admin.employeeTimeEntriesEmptyHint')}</p>
        ) : (
          <TimeActivityEntriesPanel
            embedded
            title=""
            entries={activities.entries}
            loading={activities.loading}
            error={activities.error}
            hasMore={activities.hasMore}
            loadingMore={activities.loadingMore}
            onLoadMore={activities.loadMore}
          />
        )}
      </div>
    </AppDialog>
  )
}
