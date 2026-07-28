/**
 * @file Timesheet user provisioning state for the admin app.
 */

import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  createTimesheetUser,
  fetchQboEmployees,
  fetchTimesheetUsers,
  type QboEmployeeOption,
  type QuickBooksStatus,
  type User,
} from '@ellr/api-client'
import { getApiErrorMessage, useGuardedAction, useLazyApiSelect, useLocale } from '@ellr/ui'

export type { QboEmployeeOption, User } from '@ellr/api-client'

type UseTimesheetProvisioningOptions = {
  status: QuickBooksStatus | null
  isAdministrator: boolean
  administratorTabActive: boolean
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Loads QBO employees on dropdown interaction, provisioned users on tab focus, and handles account creation.
 * @param options QuickBooks connection status, admin flag, and active administrator tab.
 * @returns Provisioning data and handlers for the administrator tab.
 */
export function useTimesheetProvisioning({
  status,
  isAdministrator,
  administratorTabActive,
  onError,
  onSuccess,
}: UseTimesheetProvisioningOptions) {
  const { locale, t } = useLocale()
  const [users, setUsers] = useState<User[]>([])
  const [loadingUsers, setLoadingUsers] = useState(false)
  const [selectedEmployee, setSelectedEmployee] = useState<QboEmployeeOption | null>(null)

  const employeesEnabled =
    isAdministrator && status?.connected === true && administratorTabActive

  const fetchEmployees = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchQboEmployees({ refresh, signal }),
    [],
  )

  const {
    items: employees,
    loading: loadingEmployees,
    loaded: employeesLoaded,
    onOpen: onEmployeeDropdownOpen,
    onClose: onEmployeeDropdownClose,
  } = useLazyApiSelect({
    enabled: employeesEnabled,
    fetch: fetchEmployees,
    onError: (message) => onError(message),
    errorMessage: t('admin.loadProvisioningFailed'),
  })

  const mappedEmployeeIds = useMemo(
    () => new Set(users.map((user) => user.qbo_employee_ref).filter(Boolean)),
    [users],
  )

  const availableEmployees = useMemo(
    () => employees.filter((employee) => !mappedEmployeeIds.has(employee.id)),
    [employees, mappedEmployeeIds],
  )

  const loadUsers = useCallback(async () => {
    if (!employeesEnabled) {
      setUsers([])
      return
    }

    setLoadingUsers(true)

    try {
      setUsers(await fetchTimesheetUsers())
    } catch (caught) {
      setUsers([])
      onError(getApiErrorMessage(caught, t('admin.loadProvisioningFailed'), locale))
    } finally {
      setLoadingUsers(false)
    }
  }, [employeesEnabled, locale, onError, t])

  useEffect(() => {
    if (!employeesEnabled) {
      setUsers([])
      return
    }

    void loadUsers()
  }, [employeesEnabled, loadUsers])

  const onEmployeeChange = (employee: QboEmployeeOption | null) => {
    setSelectedEmployee(employee)
  }

  const { run: onCreateTimesheetUser, pending: creating } = useGuardedAction(
    async (event: React.FormEvent) => {
      event.preventDefault()

      if (!selectedEmployee?.email) {
        return
      }

      try {
        const createdUser = await createTimesheetUser({
          qbo_employee_ref: selectedEmployee.id,
        })
        setUsers((current) => [...current, createdUser].sort((left, right) => left.name.localeCompare(right.name)))
        setSelectedEmployee(null)
        onSuccess(t('admin.timesheetUserCreated'))
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('admin.createTimesheetUserFailed'), locale))
      }
    },
  )

  return {
    employees: availableEmployees,
    users,
    loadingEmployees,
    employeesLoaded,
    loadingUsers,
    selectedEmployee,
    creating,
    onEmployeeChange,
    onEmployeeDropdownOpen,
    onEmployeeDropdownClose,
    onCreateTimesheetUser,
  }
}
