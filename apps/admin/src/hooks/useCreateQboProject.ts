/**
 * @file QuickBooks project creation state for the admin integrations tab.
 */

import { useCallback, useState } from 'react'
import {
  createQboProject,
  fetchAdminQboCustomers,
  type QboCustomerOption,
  type QuickBooksStatus,
} from '@ellr/api-client'
import { getApiErrorMessage, useGuardedAction, useLazyApiSelect, useLocale } from '@ellr/ui'

export type { QboCustomerOption }

type UseCreateQboProjectOptions = {
  status: QuickBooksStatus | null
  isAdministrator: boolean
  integrationsTabActive: boolean
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Manages parent-customer picker state and QuickBooks project creation.
 * @param options QuickBooks connection status, admin flag, and active integrations tab.
 * @returns Project create form state and handlers.
 */
export function useCreateQboProject({
  status,
  isAdministrator,
  integrationsTabActive,
  onError,
  onSuccess,
}: UseCreateQboProjectOptions) {
  const { locale, t } = useLocale()
  const [selectedCustomer, setSelectedCustomer] = useState<QboCustomerOption | null>(null)
  const [displayName, setDisplayName] = useState('')

  const customersEnabled =
    isAdministrator && status?.connected === true && integrationsTabActive

  const fetchCustomers = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchAdminQboCustomers({ refresh, signal }),
    [],
  )

  const {
    items: customers,
    loading: loadingCustomers,
    loaded: customersLoaded,
    onOpen: onCustomerDropdownOpen,
    onClose: onCustomerDropdownClose,
  } = useLazyApiSelect({
    enabled: customersEnabled,
    fetch: fetchCustomers,
    onError: (message) => onError(message),
    errorMessage: t('admin.loadProjectCustomersFailed'),
  })

  const { run: onCreateProject, pending: creating } = useGuardedAction(
    async (event: React.FormEvent) => {
      event.preventDefault()

      const trimmedName = displayName.trim()

      if (!selectedCustomer || trimmedName === '') {
        return
      }

      try {
        const created = await createQboProject({
          customer_ref: selectedCustomer.id,
          display_name: trimmedName,
        })
        setDisplayName('')
        onSuccess(t('admin.projectCreated', { name: created.display_name }))
      } catch (caught) {
        onError(getApiErrorMessage(caught, t('admin.createProjectFailed'), locale))
      }
    },
  )

  return {
    customers,
    loadingCustomers,
    customersLoaded,
    selectedCustomer,
    displayName,
    creating,
    onCustomerChange: setSelectedCustomer,
    onCustomerDropdownOpen,
    onCustomerDropdownClose,
    onDisplayNameChange: setDisplayName,
    onCreateProject,
  }
}
