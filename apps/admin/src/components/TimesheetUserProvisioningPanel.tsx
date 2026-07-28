/**
 * @file Timesheet user provisioning UI for QuickBooks employees.
 */

import { cardClass, inputClass, secondaryButtonClass, useLocale } from '@ellr/ui'
import type { QboEmployeeOption, User } from '../hooks/useTimesheetProvisioning'

type TimesheetUserProvisioningPanelProps = {
  connected: boolean
  employees: QboEmployeeOption[]
  users: User[]
  loadingEmployees: boolean
  syncingEmployees: boolean
  loadingUsers: boolean
  selectedEmployee: QboEmployeeOption | null
  creating: boolean
  onEmployeeChange: (employeeId: string) => void
  onRefreshEmployees: () => void
  onSubmit: (event: React.FormEvent) => void
}

/**
 * Circular arrow icon for manual QuickBooks employee sync.
 * @param props Icon presentation props.
 * @returns SVG refresh icon.
 */
function RefreshIcon({ spinning }: { spinning: boolean }) {
  return (
    <svg
      aria-hidden="true"
      className={`h-5 w-5 ${spinning ? 'animate-spin' : ''}`}
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth="1.8"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182"
      />
    </svg>
  )
}

/**
 * Administrator form to invite QuickBooks employees to the timesheet app.
 * @param props Provisioning state and handlers from `useTimesheetProvisioning`.
 * @returns Timesheet provisioning card content.
 */
export function TimesheetUserProvisioningPanel({
  connected,
  employees,
  users,
  loadingEmployees,
  syncingEmployees,
  loadingUsers,
  selectedEmployee,
  creating,
  onEmployeeChange,
  onRefreshEmployees,
  onSubmit,
}: TimesheetUserProvisioningPanelProps) {
  const { t } = useLocale()

  if (!connected) {
    return (
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">{t('admin.timesheetAccessTitle')}</h2>
        <p className="mt-2 text-sm text-slate-600">{t('admin.timesheetAccessConnectFirst')}</p>
      </section>
    )
  }

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('admin.timesheetAccessTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('admin.timesheetAccessHelp')}</p>

      <form className="mt-6 space-y-4 rounded-lg border border-slate-200 p-4" onSubmit={onSubmit}>
        <div className="flex items-end gap-2">
          <label className="block flex-1 text-sm font-medium text-slate-700">
            {t('admin.selectQboEmployee')}
            <select
              required
              className={inputClass}
              value={selectedEmployee?.id ?? ''}
              disabled={loadingEmployees || syncingEmployees}
              onChange={(event) => onEmployeeChange(event.target.value)}
            >
              <option value="">
                {loadingEmployees || syncingEmployees
                  ? t('admin.loadingEmployees')
                  : t('admin.chooseEmployee')}
              </option>
              {employees.map((employee) => (
                <option key={employee.id} value={employee.id} disabled={!employee.email}>
                  {employee.display_name}
                  {!employee.email ? ` (${t('admin.employeeMissingEmail')})` : ''}
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            onClick={onRefreshEmployees}
            disabled={loadingEmployees || syncingEmployees}
            aria-label={syncingEmployees ? t('admin.syncingEmployees') : t('admin.refreshEmployees')}
            title={syncingEmployees ? t('admin.syncingEmployees') : t('admin.refreshEmployees')}
            className={`${secondaryButtonClass} mb-0.5 px-3 py-2.5 disabled:opacity-50`}
          >
            <RefreshIcon spinning={syncingEmployees} />
          </button>
        </div>

        {!loadingEmployees && !syncingEmployees && employees.length === 0 && (
          <p className="text-sm text-slate-600">{t('admin.noEmployeesAvailable')}</p>
        )}

        {selectedEmployee && (
          <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
            <p className="font-medium text-slate-900">{t('admin.qboIdentityTitle')}</p>
            <dl className="mt-3 space-y-2">
              <div>
                <dt className="text-slate-500">{t('admin.timesheetUserName')}</dt>
                <dd className="font-medium text-slate-900">{selectedEmployee.display_name}</dd>
              </div>
              <div>
                <dt className="text-slate-500">{t('admin.timesheetUserEmail')}</dt>
                <dd className="font-medium text-slate-900">{selectedEmployee.email}</dd>
              </div>
            </dl>
            <p className="mt-3 text-slate-600">{t('admin.qboIdentityReadOnly')}</p>
          </div>
        )}

        <button
          type="submit"
          disabled={creating || !selectedEmployee?.email}
          className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
        >
          {creating ? t('admin.creatingTimesheetUser') : t('admin.createTimesheetUser')}
        </button>
      </form>

      <div className="mt-6">
        <h3 className="text-sm font-medium text-slate-900">{t('admin.provisionedUsersTitle')}</h3>
        {loadingUsers ? (
          <p className="mt-2 text-sm text-slate-600">{t('admin.loadingUsers')}</p>
        ) : users.length === 0 ? (
          <p className="mt-2 text-sm text-slate-600">{t('admin.noProvisionedUsers')}</p>
        ) : (
          <ul className="mt-3 divide-y divide-slate-200 rounded-lg border border-slate-200">
            {users.map((user) => (
              <li key={user.id} className="px-4 py-3 text-sm">
                <p className="font-medium text-slate-900">{user.name}</p>
                <p className="text-slate-600">{user.email}</p>
                <p className="text-slate-500">
                  {t('admin.mappedEmployee', {
                    name: user.qbo_employee_name ?? user.qbo_employee_ref ?? '',
                  })}
                </p>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  )
}
