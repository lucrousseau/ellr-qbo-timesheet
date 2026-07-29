/**
 * @file Client assignment dialog state and API orchestration for the admin app.
 */

import {
  fetchAdminQboCustomers,
  fetchTimesheetUserCustomers,
  normalizeQboRef,
  syncTimesheetUserCustomers,
  type QboCustomerOption,
  type TimesheetUserCustomerAccess,
} from '@ellr/api-client'
import { getApiErrorMessage, useGuardedAction, useLocale } from '@ellr/ui'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { User } from './useTimesheetProvisioning'

export type { QboCustomerOption, TimesheetUserCustomerAccess } from '@ellr/api-client'
export { normalizeQboRef } from '@ellr/api-client'

type UseManageTimesheetUserClientsOptions = {
  user: User | null
  onClose: () => void
  onSaved: (userId: number, access: TimesheetUserCustomerAccess) => void
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Applies customer access settings to dialog state.
 * @param access Saved or loaded customer access payload.
 * @returns Normalized selected customer identifiers.
 */
function selectedRefsFromAccess(access: TimesheetUserCustomerAccess): string[] {
  return access.data.map((customer) => normalizeQboRef(customer.id))
}

/**
 * Loads and saves QuickBooks client access for a provisioned timesheet user.
 * @param options Target user, save handlers, and dialog lifecycle callbacks.
 * @returns Dialog state and handlers for the client assignment UI.
 */
export function useManageTimesheetUserClients({
  user,
  onClose,
  onSaved,
  onError,
  onSuccess,
}: UseManageTimesheetUserClientsOptions) {
  const { locale, t } = useLocale()
  const userId = user?.id ?? null
  const seededAllCustomersAccess = user?.all_customers_access === true
  const [loading, setLoading] = useState(false)
  const [accessReady, setAccessReady] = useState(false)
  const [availableCustomers, setAvailableCustomers] = useState<QboCustomerOption[]>([])
  const [allCustomersAccess, setAllCustomersAccess] = useState(seededAllCustomersAccess)
  const [selectedRefs, setSelectedRefs] = useState<string[]>(
    () => user?.assigned_customers?.map((customer) => normalizeQboRef(customer.id)) ?? [],
  )
  const [search, setSearch] = useState('')
  const onErrorRef = useRef(onError)
  const onCloseRef = useRef(onClose)

  const displayAllCustomersAccess = accessReady ? allCustomersAccess : seededAllCustomersAccess
  const showClientAssignment = !displayAllCustomersAccess

  onErrorRef.current = onError
  onCloseRef.current = onClose

  const applyAccessState = useCallback((access: TimesheetUserCustomerAccess) => {
    setAllCustomersAccess(access.all_customers_access)
    setSelectedRefs(selectedRefsFromAccess(access))
  }, [])

  useEffect(() => {
    if (userId === null) {
      setAvailableCustomers([])
      setAllCustomersAccess(false)
      setSelectedRefs([])
      setSearch('')
      setLoading(false)
      setAccessReady(false)
      return
    }

    setAccessReady(false)
    setAllCustomersAccess(user?.all_customers_access === true)
    setSelectedRefs(
      user?.assigned_customers?.map((customer) => normalizeQboRef(customer.id)) ?? [],
    )
    setSearch('')

    let cancelled = false

    const loadCustomers = async () => {
      setLoading(true)

      try {
        const [available, access] = await Promise.all([
          fetchAdminQboCustomers({ refresh: true }),
          fetchTimesheetUserCustomers(userId),
        ])

        if (cancelled) {
          return
        }

        setAvailableCustomers(available)
        applyAccessState(access)
        setSearch('')
      } catch (caught) {
        if (cancelled) {
          return
        }

        onErrorRef.current(
          getApiErrorMessage(caught, t('admin.loadClientAssignmentsFailed'), locale),
        )
        onCloseRef.current()
      } finally {
        if (!cancelled) {
          setLoading(false)
          setAccessReady(true)
        }
      }
    }

    void loadCustomers()

    return () => {
      cancelled = true
    }
    // Keyed on userId only; userToManageClients is stable while the dialog stays open.
    // oxlint-disable-next-line react-hooks/exhaustive-deps
  }, [applyAccessState, locale, t, userId])

  const filteredCustomers = useMemo(() => {
    const query = search.trim().toLowerCase()

    if (query === '') {
      return availableCustomers
    }

    return availableCustomers.filter((customer) =>
      customer.display_name.toLowerCase().includes(query),
    )
  }, [availableCustomers, search])

  const isCustomerSelected = useCallback(
    (customerId: string | number) =>
      selectedRefs.some((ref) => normalizeQboRef(ref) === normalizeQboRef(customerId)),
    [selectedRefs],
  )

  const toggleCustomer = (customerId: string | number) => {
    const normalizedId = normalizeQboRef(customerId)

    setSelectedRefs((current) =>
      current.some((ref) => normalizeQboRef(ref) === normalizedId)
        ? current.filter((ref) => normalizeQboRef(ref) !== normalizedId)
        : [...current, normalizedId],
    )
  }

  const { run: saveAssignments, pending: saving } = useGuardedAction(async () => {
    if (userId === null) {
      return
    }

    try {
      const access = await syncTimesheetUserCustomers(userId, {
        all_customers_access: allCustomersAccess,
        customer_refs: allCustomersAccess ? [] : selectedRefs.map((ref) => normalizeQboRef(ref)),
      })
      applyAccessState(access)
      onSaved(userId, access)
      onSuccess(t('admin.clientAssignmentsSaved'))
      onClose()
    } catch (caught) {
      onError(getApiErrorMessage(caught, t('admin.saveClientAssignmentsFailed'), locale))
    }
  })

  return {
    loading,
    saving,
    search,
    setSearch,
    displayAllCustomersAccess,
    showClientAssignment,
    accessReady,
    filteredCustomers,
    selectedRefs,
    isCustomerSelected,
    toggleCustomer,
    setAllCustomersAccess,
    saveAssignments,
  }
}
