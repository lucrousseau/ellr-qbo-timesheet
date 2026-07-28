/**
 * @file Tabbed admin dashboard grouped by responsibility.
 */

import { Alert, TabNav, tabPanelId, useLocale } from '@ellr/ui'
import { useMemo, useState } from 'react'
import { AccountPanel } from './AccountPanel'
import { QuickBooksConnectionPanel } from './QuickBooksConnectionPanel'
import { TimesheetUserProvisioningPanel } from './TimesheetUserProvisioningPanel'
import type { useQuickBooksAdmin } from '../hooks/useQuickBooksAdmin'
import { useTimesheetProvisioning } from '../hooks/useTimesheetProvisioning'

const TAB_ID_PREFIX = 'admin'

type AdminTab = 'preferences' | 'administrator'

type AdminDashboardProps = {
  admin: ReturnType<typeof useQuickBooksAdmin>
}

/**
 * Authenticated admin workspace with preferences and administrator tabs.
 * @param props QuickBooks admin hook state and handlers.
 * @returns Tabbed dashboard content.
 */
export function AdminDashboard({ admin }: AdminDashboardProps) {
  const { t } = useLocale()
  const [activeTab, setActiveTab] = useState<AdminTab>('preferences')
  const isAdministrator = admin.user?.is_admin === true

  const tabs = useMemo(() => {
    const items: { id: AdminTab; label: string }[] = [
      { id: 'preferences', label: t('admin.tabPreferences') },
    ]

    if (isAdministrator) {
      items.push({ id: 'administrator', label: t('admin.tabAdministrator') })
    }

    return items
  }, [isAdministrator, t])

  const activeTabId = tabs.some((tab) => tab.id === activeTab) ? activeTab : 'preferences'

  const provisioning = useTimesheetProvisioning({
    status: admin.status,
    isAdministrator,
    administratorTabActive: activeTabId === 'administrator',
    onError: admin.showError,
    onSuccess: admin.showSuccess,
  })

  return (
    <div className="space-y-6">
      {admin.message && (
        <Alert variant={admin.message.type}>{admin.message.text}</Alert>
      )}

      {admin.bootstrapError && (
        <Alert variant="error">{admin.bootstrapError}</Alert>
      )}

      {tabs.length > 1 && (
        <TabNav
          items={tabs}
          activeId={activeTabId}
          onChange={setActiveTab}
          ariaLabel={t('admin.tabsLabel')}
          idPrefix={TAB_ID_PREFIX}
        />
      )}

      {activeTabId === 'preferences' ? (
        <AccountPanel
          preferenceLocale={admin.preferenceLocale}
          savingLocale={admin.savingLocale}
          onLocaleChange={admin.setPreferenceLocale}
          onSaveLocale={admin.saveLocale}
          tabIdPrefix={TAB_ID_PREFIX}
        />
      ) : (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'administrator')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-administrator`}
          className="space-y-6"
        >
          <QuickBooksConnectionPanel
            bootstrapError={null}
            message={null}
            status={admin.status}
            connecting={admin.connecting}
            disconnecting={admin.disconnecting}
            onConnect={admin.connectQuickBooksFlow}
            onDisconnect={admin.disconnectQuickBooksFlow}
          />
          <TimesheetUserProvisioningPanel
            connected={admin.status?.connected === true}
            employees={provisioning.employees}
            users={provisioning.users}
            loadingEmployees={provisioning.loadingEmployees}
            employeesLoaded={provisioning.employeesLoaded}
            loadingUsers={provisioning.loadingUsers}
            selectedEmployee={provisioning.selectedEmployee}
            creating={provisioning.creating}
            onEmployeeChange={provisioning.onEmployeeChange}
            onEmployeeDropdownOpen={provisioning.onEmployeeDropdownOpen}
            onEmployeeDropdownClose={provisioning.onEmployeeDropdownClose}
            onSubmit={provisioning.onCreateTimesheetUser}
          />
        </div>
      )}
    </div>
  )
}
