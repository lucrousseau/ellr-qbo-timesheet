/**
 * @file Admin self-mapping to a QuickBooks employee for timesheet entry.
 */

import { useCallback, useMemo, useState } from 'react'
import {
  fetchQboEmployees,
  updateQboEmployee,
  type QboEmployeeOption,
  type QuickBooksStatus,
  type User,
} from '@ellr/api-client'
import { getApiErrorMessage, useGuardedAction, useLazyApiSelect, useLocale } from '@ellr/ui'

export type { QboEmployeeOption }

type UseAdminQboEmployeeMappingOptions = {
  status: QuickBooksStatus | null
  isAdministrator: boolean
  integrationsTabActive: boolean
  user: User | null
  setUser: (user: User) => void
  /** Employee refs already linked to timesheet users (not the admin). */
  occupiedEmployeeRefs: ReadonlySet<string>
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Lazy QBO employee picker and save flow for linking the signed-in admin account.
 * @param options Connection status, current user, and occupied employee refs.
 * @returns Mapping form state and handlers for the integrations tab.
 */
export function useAdminQboEmployeeMapping({
  status,
  isAdministrator,
  integrationsTabActive,
  user,
  setUser,
  occupiedEmployeeRefs,
  onError,
  onSuccess,
}: UseAdminQboEmployeeMappingOptions) {
  const { locale, t } = useLocale()
  const [selectedEmployee, setSelectedEmployee] = useState<QboEmployeeOption | null>(null)

  const employeesEnabled =
    isAdministrator && status?.connected === true && integrationsTabActive

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

  const currentRef = user?.qbo_employee_ref ?? null

  const availableEmployees = useMemo(
    () =>
      employees.filter((employee) => {
        if (currentRef !== null && employee.id === currentRef) {
          return true
        }

        return !occupiedEmployeeRefs.has(employee.id)
      }),
    [currentRef, employees, occupiedEmployeeRefs],
  )

  const mappedEmployeeLabel = useMemo(() => {
    if (!currentRef) {
      return null
    }

    const fromList = availableEmployees.find((employee) => employee.id === currentRef)
    if (fromList) {
      return fromList.display_name
    }

    if (selectedEmployee?.id === currentRef) {
      return selectedEmployee.display_name
    }

    return currentRef
  }, [availableEmployees, currentRef, selectedEmployee])

  const onEmployeeChange = (employee: QboEmployeeOption | null) => {
    setSelectedEmployee(employee)
  }

  const { run: onSaveMapping, pending: saving } = useGuardedAction(
    async (event: React.FormEvent) => {
      event.preventDefault()

      if (!selectedEmployee?.email || selectedEmployee.id === currentRef) {
        return
      }

      try {
        const updated = await updateQboEmployee(selectedEmployee.id)
        setUser(updated)
        onSuccess(t('admin.employeeSaved'))
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('admin.saveEmployeeFailed'), locale))
      }
    },
  )

  return {
    employees: availableEmployees,
    loadingEmployees,
    employeesLoaded,
    selectedEmployee,
    currentRef,
    mappedEmployeeLabel,
    saving,
    onEmployeeChange,
    onEmployeeDropdownOpen,
    onEmployeeDropdownClose,
    onSaveMapping,
  }
}
