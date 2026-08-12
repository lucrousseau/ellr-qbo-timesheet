/**
 * @file Dialog showing editable time entries for a provisioned employee.
 */

import { AppDialog, TimeActivityEntriesPanel, useLocale } from '@ellr/ui'
import type { User } from '../hooks/useTimesheetProvisioning'
import { useAdminUserTimeActivities } from '../hooks/useAdminUserTimeActivities'

type EmployeeTimeEntriesDialogProps = {
  user: User | null
  onClose: () => void
  onSuccess: (message: string) => void
  onError: (message: string) => void
}

/**
 * Administrator dialog to review and edit an employee's recent time entries.
 * @param props Target user, close handler, and flash message callbacks.
 * @returns Employee time entries dialog or null when closed.
 */
export function EmployeeTimeEntriesDialog({
  user,
  onClose,
  onSuccess,
  onError,
}: EmployeeTimeEntriesDialogProps) {
  const { t } = useLocale()
  const activities = useAdminUserTimeActivities({
    userId: user?.id ?? null,
    enabled: user !== null,
    onSuccess,
    onError,
  })

  return (
    <AppDialog
      open={user !== null}
      title={t('admin.employeeTimeEntriesTitle', { name: user?.name ?? '' })}
      onClose={onClose}
      panelClassName="w-full max-w-6xl rounded-xl border border-brand-border bg-white p-6 shadow-xl"
    >
      <p className="text-sm text-brand-muted">{t('admin.employeeTimeEntriesHelp')}</p>

      <div className="mt-4">
        <TimeActivityEntriesPanel
          embedded
          title=""
          entries={activities.entries}
          loading={activities.loading}
          error={activities.error}
          hasMore={activities.hasMore}
          loadingMore={activities.loadingMore}
          onLoadMore={activities.loadMore}
          editable
          savingId={activities.savingId}
          onSaveEntry={activities.saveEntry}
        />
      </div>
    </AppDialog>
  )
}
