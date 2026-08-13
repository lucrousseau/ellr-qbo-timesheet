/**
 * @file Admin UI to link the signed-in administrator to a QuickBooks employee.
 */

import {
  Button,
  cardClass,
  LazySearchCombobox,
  nestedPanelClass,
  sectionHelpClass,
  sectionTitleClass,
  useLocale,
} from '@ellr/ui'
import type { QboEmployeeOption } from '../hooks/useAdminQboEmployeeMapping'

type AdminQboEmployeeMappingPanelProps = {
  connected: boolean
  employees: QboEmployeeOption[]
  loadingEmployees: boolean
  employeesLoaded: boolean
  selectedEmployee: QboEmployeeOption | null
  currentRef: string | null
  mappedEmployeeLabel: string | null
  saving: boolean
  onEmployeeChange: (employee: QboEmployeeOption | null) => void
  onEmployeeDropdownOpen: () => void
  onEmployeeDropdownClose: () => void
  onSubmit: (event: React.FormEvent) => void
}

/**
 * Form for an administrator to map their own account to a QBO employee.
 * @param props Mapping state and handlers from `useAdminQboEmployeeMapping`.
 * @returns Self-mapping card content.
 */
export function AdminQboEmployeeMappingPanel({
  connected,
  employees,
  loadingEmployees,
  employeesLoaded,
  selectedEmployee,
  currentRef,
  mappedEmployeeLabel,
  saving,
  onEmployeeChange,
  onEmployeeDropdownOpen,
  onEmployeeDropdownClose,
  onSubmit,
}: AdminQboEmployeeMappingPanelProps) {
  const { t } = useLocale()

  if (!connected) {
    return (
      <section className={cardClass}>
        <h2 className={sectionTitleClass}>{t('admin.quickbooksEmployeeTitle')}</h2>
        <p className={sectionHelpClass}>{t('admin.quickbooksEmployeeConnectFirst')}</p>
      </section>
    )
  }

  const canSubmit =
    selectedEmployee !== null &&
    Boolean(selectedEmployee.email) &&
    selectedEmployee.id !== currentRef &&
    !saving

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleClass}>{t('admin.quickbooksEmployeeTitle')}</h2>
      <p className={sectionHelpClass}>{t('admin.quickbooksEmployeeHelp')}</p>

      {currentRef ? (
        <p className="mt-4 text-sm text-brand-muted">
          {t('admin.myEmployeeMapped', { label: mappedEmployeeLabel ?? currentRef })}
        </p>
      ) : (
        <p className="mt-4 text-sm text-brand-muted">{t('admin.myEmployeeUnlinked')}</p>
      )}

      <form className="mt-6 space-y-4 rounded-[9px] border border-brand-border p-4" onSubmit={onSubmit}>
        <LazySearchCombobox
          label={t('admin.selectMyQboEmployee')}
          placeholder={t('admin.chooseMyEmployee')}
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
          <div className={nestedPanelClass}>
            <p className="font-medium text-brand-primary">{t('admin.qboIdentityTitle')}</p>
            <dl className="mt-3 space-y-2">
              <div>
                <dt className="text-brand-muted-subtle">{t('admin.timesheetUserName')}</dt>
                <dd className="font-medium text-brand-primary">{selectedEmployee.display_name}</dd>
              </div>
              <div>
                <dt className="text-brand-muted-subtle">{t('admin.timesheetUserEmail')}</dt>
                <dd className="font-medium text-brand-primary">{selectedEmployee.email}</dd>
              </div>
            </dl>
            <p className="mt-3 text-brand-muted">{t('admin.myEmployeeEmailKept')}</p>
          </div>
        )}

        <Button type="submit" disabled={!canSubmit}>
          {saving ? t('admin.savingEmployee') : t('admin.saveEmployee')}
        </Button>
      </form>
    </section>
  )
}
