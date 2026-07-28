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
}: TimesheetProvisionedUserRowProps) {
  const { t } = useLocale()

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
      </div>
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
    </li>
  )
}
