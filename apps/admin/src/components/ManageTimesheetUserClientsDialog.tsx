/**
 * @file Dialog for assigning QuickBooks clients to a provisioned timesheet user.
 */

import {
  fetchAdminQboCustomers,
  fetchTimesheetUserCustomers,
  normalizeQboRef,
  syncTimesheetUserCustomers,
  type QboCustomerOption,
  type TimesheetUserCustomerAccess,
} from '@ellr/api-client'
import { AppDialog, Button, getApiErrorMessage, TextField, useGuardedAction, useLocale } from '@ellr/ui'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { User } from '../hooks/useTimesheetProvisioning'

type ManageTimesheetUserClientsDialogProps = {
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
 * Lets administrators choose which QuickBooks clients a timesheet user may access.
 * @param props Target user, save handlers, and dialog lifecycle callbacks.
 * @returns Client assignment dialog or null when closed.
 */
export function ManageTimesheetUserClientsDialog({
  user,
  onClose,
  onSaved,
  onError,
  onSuccess,
}: ManageTimesheetUserClientsDialogProps) {
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

  return (
    <AppDialog
      open={user !== null}
      title={t('admin.manageClientAccessTitle', { name: user?.name ?? '' })}
      onClose={onClose}
      footer={
        <>
          <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button
            type="button"
            disabled={loading || saving}
            onClick={() => {
              void saveAssignments()
            }}
          >
            {saving ? t('common.saving') : t('common.save')}
          </Button>
        </>
      }
    >
      <p className="text-sm text-slate-600">{t('admin.manageClientAccessHelp')}</p>

      <label className="mt-4 flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
        <input
          type="checkbox"
          className="mt-0.5 size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
          checked={displayAllCustomersAccess}
          disabled={loading || saving}
          onChange={(event) => setAllCustomersAccess(event.target.checked)}
        />
        <span>
          <span className="block font-medium text-slate-900">{t('admin.allClientsAccess')}</span>
          <span className="mt-1 block text-slate-600">{t('admin.allClientsAccessHelp')}</span>
        </span>
      </label>

      {showClientAssignment && !accessReady ? (
        <p className="mt-4 text-sm text-slate-600">{t('admin.loadingClients')}</p>
      ) : null}

      {showClientAssignment && accessReady ? (
        <>
          <div className="mt-4">
            <TextField
              label={t('admin.searchClients')}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={t('admin.searchClientsPlaceholder')}
            />
          </div>

          {filteredCustomers.length === 0 ? (
            <p className="mt-4 text-sm text-slate-600">{t('admin.noClientsAvailable')}</p>
          ) : (
            <ul className="mt-4 max-h-72 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">
              {filteredCustomers.map((customer) => {
                const checked = isCustomerSelected(customer.id)
                const checkboxId = `client-${normalizeQboRef(customer.id)}`

                return (
                  <li key={checkboxId}>
                    <label htmlFor={checkboxId} className="flex cursor-pointer items-center gap-3 text-sm">
                      <input
                        id={checkboxId}
                        type="checkbox"
                        className="size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                        checked={checked}
                        onChange={() => toggleCustomer(customer.id)}
                      />
                      <span className="font-medium text-slate-900">{customer.display_name}</span>
                    </label>
                  </li>
                )
              })}
            </ul>
          )}

          <p className="mt-3 text-sm text-slate-500">
            {t('admin.selectedClientCount', { count: selectedRefs.length })}
          </p>
        </>
      ) : null}
    </AppDialog>
  )
}
