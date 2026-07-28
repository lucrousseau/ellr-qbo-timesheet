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
import { getApiErrorMessage, useLocale } from '@ellr/ui'

export type { QboEmployeeOption, User } from '@ellr/api-client'

type UseTimesheetProvisioningOptions = {
  status: QuickBooksStatus | null
  isAdministrator: boolean
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Loads QBO employees, provisioned users, and handles timesheet account creation.
 * @param options QuickBooks connection status and admin flag.
 * @returns Provisioning data and handlers for the administrator tab.
 */
export function useTimesheetProvisioning({
  status,
  isAdministrator,
  onError,
  onSuccess,
}: UseTimesheetProvisioningOptions) {
  const { locale, t } = useLocale()
  const [employees, setEmployees] = useState<QboEmployeeOption[]>([])
  const [users, setUsers] = useState<User[]>([])
  const [loadingEmployees, setLoadingEmployees] = useState(false)
  const [syncingEmployees, setSyncingEmployees] = useState(false)
  const [loadingUsers, setLoadingUsers] = useState(false)
  const [selectedEmployeeId, setSelectedEmployeeId] = useState('')
  const [creating, setCreating] = useState(false)

  const mappedEmployeeIds = useMemo(
    () => new Set(users.map((user) => user.qbo_employee_ref).filter(Boolean)),
    [users],
  )

  const availableEmployees = useMemo(
    () => employees.filter((employee) => !mappedEmployeeIds.has(employee.id)),
    [employees, mappedEmployeeIds],
  )

  const selectedEmployee = useMemo(
    () => employees.find((employee) => employee.id === selectedEmployeeId) ?? null,
    [employees, selectedEmployeeId],
  )

  const loadUsers = useCallback(async () => {
    if (!isAdministrator || status?.connected !== true) {
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
  }, [isAdministrator, locale, onError, status?.connected, t])

  const loadEmployees = useCallback(async (refresh = false): Promise<{ employees: QboEmployeeOption[]; ok: boolean }> => {
    if (!isAdministrator || status?.connected !== true) {
      setEmployees([])
      return { employees: [], ok: false }
    }

    if (refresh) {
      setSyncingEmployees(true)
    } else {
      setLoadingEmployees(true)
    }

    try {
      const data = await fetchQboEmployees({ refresh })
      setEmployees(data)

      return { employees: data, ok: true }
    } catch (caught) {
      setEmployees([])
      onError(getApiErrorMessage(caught, t('admin.loadProvisioningFailed'), locale))

      return { employees: [], ok: false }
    } finally {
      setLoadingEmployees(false)
      setSyncingEmployees(false)
    }
  }, [isAdministrator, locale, onError, status?.connected, t])

  const refreshEmployees = useCallback(async () => {
    const result = await loadEmployees(true)

    if (result.ok) {
      onSuccess(t('admin.employeesSynced'))
    }
  }, [loadEmployees, onSuccess, t])

  useEffect(() => {
    if (!isAdministrator || status?.connected !== true) {
      setEmployees([])
      setUsers([])
      return
    }

    void (async () => {
      const initialResult = await loadEmployees()

      if (initialResult.employees.length === 0) {
        await loadEmployees(true)
      }

      await loadUsers()
    })()
  }, [isAdministrator, loadEmployees, loadUsers, status?.connected])

  const onEmployeeChange = (employeeId: string) => {
    setSelectedEmployeeId(employeeId)
  }

  const onCreateTimesheetUser = async (event: React.FormEvent) => {
    event.preventDefault()

    if (!selectedEmployee?.email) {
      return
    }

    setCreating(true)

    try {
      const createdUser = await createTimesheetUser({
        qbo_employee_ref: selectedEmployee.id,
      })
      setUsers((current) => [...current, createdUser].sort((left, right) => left.name.localeCompare(right.name)))
      setSelectedEmployeeId('')
      onSuccess(t('admin.timesheetUserCreated'))
    } catch (caught) {
      onError(getApiErrorMessage(caught, t('admin.createTimesheetUserFailed'), locale))
    } finally {
      setCreating(false)
    }
  }

  return {
    employees: availableEmployees,
    users,
    loadingEmployees,
    syncingEmployees,
    loadingUsers,
    selectedEmployee,
    creating,
    onEmployeeChange,
    onCreateTimesheetUser,
    refreshEmployees,
  }
}
