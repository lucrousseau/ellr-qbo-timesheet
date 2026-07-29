/**
 * @file Dialog for assigning QuickBooks clients to a provisioned timesheet user.
 */

import { AppDialog, Button, TextField, useLocale } from '@ellr/ui'
import { normalizeQboRef } from '../hooks/useManageTimesheetUserClients'
import type { User } from '../hooks/useTimesheetProvisioning'
import type { useManageTimesheetUserClients } from '../hooks/useManageTimesheetUserClients'

type ManageTimesheetUserClientsDialogProps = {
  user: User | null
  onClose: () => void
  dialog: ReturnType<typeof useManageTimesheetUserClients>
}

/**
 * Lets administrators choose which QuickBooks clients a timesheet user may access.
 * @param props Target user, close handler, and dialog state from `useManageTimesheetUserClients`.
 * @returns Client assignment dialog or null when closed.
 */
export function ManageTimesheetUserClientsDialog({
  user,
  onClose,
  dialog,
}: ManageTimesheetUserClientsDialogProps) {
  const { t } = useLocale()
  const {
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
  } = dialog

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
