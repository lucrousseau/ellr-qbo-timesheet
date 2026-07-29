/**
 * @file Single row in the provisioned timesheet users list.
 */

import { Button, useLocale } from '@ellr/ui'
import type { User } from '../hooks/useTimesheetProvisioning'

type TimesheetProvisionedUserRowProps = {
  user: User
  removing: boolean
  removingUserId: number | null
  onRequestRemove: (user: User) => void
  onManageClients: (user: User) => void
  onViewTimeEntries: (user: User) => void
}

/**
 * Displays a provisioned user and a remove-access action.
 * @param props User row data and remove handler.
 * @returns List item markup.
 */
export function TimesheetProvisionedUserRow({
  user,
  removing,
  removingUserId,
  onRequestRemove,
  onManageClients,
  onViewTimeEntries,
}: TimesheetProvisionedUserRowProps) {
  const { t } = useLocale()
  const assignedCount = user.assigned_customers?.length ?? 0
  const hasAllClientsAccess = user.all_customers_access === true

  return (
    <li className="flex items-start justify-between gap-4 px-4 py-3 text-sm">
      <div>
        <p className="font-medium text-slate-900">{user.name}</p>
        <p className="text-slate-600">{user.email}</p>
        <p className="text-slate-500">
          {t('admin.mappedEmployee', {
            name: user.qbo_employee_name ?? user.qbo_employee_ref ?? '',
          })}
        </p>
        <p className="text-slate-500">
          {hasAllClientsAccess
            ? t('admin.allClientsAccessEnabled')
            : assignedCount === 0
              ? t('admin.noAssignedClients')
              : t('admin.assignedClientCount', { count: assignedCount })}
        </p>
      </div>
      <div className="flex shrink-0 flex-col items-end gap-2">
        <Button
          type="button"
          variant="secondary"
          size="compact"
          disabled={removing}
          onClick={() => onViewTimeEntries(user)}
        >
          {t('admin.viewTimeEntries')}
        </Button>
        <Button
          type="button"
          variant="secondary"
          size="compact"
          disabled={removing}
          onClick={() => onManageClients(user)}
        >
          {t('admin.manageClientAccess')}
        </Button>
        <Button
          type="button"
          variant="danger"
          size="compact"
          disabled={removing}
          aria-label={t('admin.removeTimesheetAccessFor', { name: user.name })}
          onClick={() => onRequestRemove(user)}
        >
          {removingUserId === user.id
            ? t('admin.removingTimesheetAccess')
            : t('admin.removeTimesheetAccess')}
        </Button>
      </div>
    </li>
  )
}
