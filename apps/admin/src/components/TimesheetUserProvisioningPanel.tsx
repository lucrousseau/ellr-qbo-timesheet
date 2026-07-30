/**
 * @file Timesheet user provisioning UI for QuickBooks employees.
 */

import { Button, cardClass, LazySearchCombobox, useLocale } from '@ellr/ui'
import { useCallback, useState } from 'react'
import { AssignSupervisorDialog } from './AssignSupervisorDialog'
import { ManageTimesheetUserClientsDialog } from './ManageTimesheetUserClientsDialog'
import { EmployeeTimeEntriesDialog } from './EmployeeTimeEntriesDialog'
import { RemoveTimesheetAccessDialog } from './RemoveTimesheetAccessDialog'
import { TimesheetProvisionedUserRow } from './TimesheetProvisionedUserRow'
import { useManageTimesheetUserClients } from '../hooks/useManageTimesheetUserClients'
import type { QboEmployeeOption, TimesheetUserCustomerAccess, User } from '../hooks/useTimesheetProvisioning'

type TimesheetUserProvisioningPanelProps = {
  connected: boolean
  employees: QboEmployeeOption[]
  users: User[]
  loadingEmployees: boolean
  employeesLoaded: boolean
  loadingUsers: boolean
  selectedEmployee: QboEmployeeOption | null
  creating: boolean
  removing: boolean
  removingUserId: number | null
  onEmployeeChange: (employee: QboEmployeeOption | null) => void
  onEmployeeDropdownOpen: () => void
  onEmployeeDropdownClose: () => void
  onSubmit: (event: React.FormEvent) => void
  onRemoveTimesheetUser: (userId: number) => Promise<boolean>
  onClientAssignmentsSaved: (userId: number, access: TimesheetUserCustomerAccess) => void
  onSupervisorSaved: (userId: number, supervisorId: number | null) => void
  onError: (message: string) => void
  onSuccess: (message: string) => void
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
  removing,
  removingUserId,
  onEmployeeChange,
  onEmployeeDropdownOpen,
  onEmployeeDropdownClose,
  onSubmit,
  onRemoveTimesheetUser,
  onClientAssignmentsSaved,
  onSupervisorSaved,
  onError,
  onSuccess,
}: TimesheetUserProvisioningPanelProps) {
  const { t } = useLocale()
  const [userToRemove, setUserToRemove] = useState<User | null>(null)
  const [userToManageClients, setUserToManageClients] = useState<User | null>(null)
  const [userToViewEntries, setUserToViewEntries] = useState<User | null>(null)
  const [userToAssignSupervisor, setUserToAssignSupervisor] = useState<User | null>(null)
  const closeClientDialog = useCallback(() => setUserToManageClients(null), [])
  const closeEntriesDialog = useCallback(() => setUserToViewEntries(null), [])
  const manageClientsDialog = useManageTimesheetUserClients({
    user: userToManageClients,
    onClose: closeClientDialog,
    onSaved: onClientAssignmentsSaved,
    onError,
    onSuccess,
  })

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

        <Button
          type="submit"
          variant="secondary"
          disabled={creating || !selectedEmployee?.email}
        >
          {creating ? t('admin.creatingTimesheetUser') : t('admin.createTimesheetUser')}
        </Button>
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
              <TimesheetProvisionedUserRow
                key={user.id}
                user={user}
                supervisorName={
                  user.supervisor_id
                    ? users.find((candidate) => candidate.id === user.supervisor_id)?.name ?? null
                    : null
                }
                removing={removing}
                removingUserId={removingUserId}
                onRequestRemove={setUserToRemove}
                onManageClients={setUserToManageClients}
                onViewTimeEntries={setUserToViewEntries}
                onAssignSupervisor={setUserToAssignSupervisor}
              />
            ))}
          </ul>
        )}
      </div>

      <RemoveTimesheetAccessDialog
        user={userToRemove}
        confirming={removing}
        onConfirm={onRemoveTimesheetUser}
        onClose={() => setUserToRemove(null)}
      />

      <ManageTimesheetUserClientsDialog
        user={userToManageClients}
        onClose={closeClientDialog}
        dialog={manageClientsDialog}
      />

      <EmployeeTimeEntriesDialog
        user={userToViewEntries}
        onClose={closeEntriesDialog}
        onSuccess={onSuccess}
        onError={onError}
      />

      <AssignSupervisorDialog
        user={userToAssignSupervisor}
        supervisorOptions={users}
        onClose={() => setUserToAssignSupervisor(null)}
        onSaved={(userId, supervisorId) => {
          onSupervisorSaved(userId, supervisorId)
          onSuccess(t('admin.assignSupervisorSuccess'))
        }}
        onError={onError}
      />
    </section>
  )
}
