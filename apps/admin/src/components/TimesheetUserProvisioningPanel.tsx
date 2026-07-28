/**
 * @file Timesheet user provisioning UI for QuickBooks employees.
 */

import { cardClass, LazySearchCombobox, secondaryButtonClass, useLocale } from '@ellr/ui'
import type { QboEmployeeOption, User } from '../hooks/useTimesheetProvisioning'

type TimesheetUserProvisioningPanelProps = {
  connected: boolean
  employees: QboEmployeeOption[]
  users: User[]
  loadingEmployees: boolean
  employeesLoaded: boolean
  loadingUsers: boolean
  selectedEmployee: QboEmployeeOption | null
  creating: boolean
  onEmployeeChange: (employee: QboEmployeeOption | null) => void
  onEmployeeDropdownOpen: () => void
  onEmployeeDropdownClose: () => void
  onSubmit: (event: React.FormEvent) => void
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
  employeesLoaded,
  loadingUsers,
  selectedEmployee,
  creating,
  onEmployeeChange,
  onEmployeeDropdownOpen,
  onEmployeeDropdownClose,
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
        <LazySearchCombobox
          label={t('admin.selectQboEmployee')}
          placeholder={t('admin.chooseEmployee')}
          searchPlaceholder={t('admin.searchEmployees')}
          loadingLabel={t('admin.loadingEmployees')}
          emptyLabel={t('admin.noEmployeesAvailable')}
          noResultsLabel={t('admin.noEmployeeSearchResults')}
          value={selectedEmployee}
          options={employees}
          loading={loadingEmployees}
          loaded={employeesLoaded}
          onLoad={onEmployeeDropdownOpen}
          onClose={onEmployeeDropdownClose}
          onChange={onEmployeeChange}
          getOptionValue={(employee) => employee.id}
          getOptionLabel={(employee) => employee.display_name}
          isOptionDisabled={(employee) => !employee.email}
          getOptionHint={(employee) => (employee.email ? null : t('admin.employeeMissingEmail'))}
        />

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
