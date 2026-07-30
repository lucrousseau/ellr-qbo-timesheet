/**
 * @file Tabbed admin dashboard grouped by responsibility.
 */

import { Alert, TabNav, tabPanelId, useLocale } from '@ellr/ui'
import { useEffect, useMemo } from 'react'
import {
  LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY,
  useAdminActiveTab,
  type AdminTab,
} from '../adminTabStorage'
import { AccountPanel } from './AccountPanel'
import { QuickBooksConnectionPanel } from './QuickBooksConnectionPanel'
import { TimesheetUserProvisioningPanel } from './TimesheetUserProvisioningPanel'
import { SuperAdminOrganizationsPanel } from './SuperAdminOrganizationsPanel'
import type { useQuickBooksAdmin } from '../hooks/useQuickBooksAdmin'
import { useTimesheetProvisioning } from '../hooks/useTimesheetProvisioning'
import { useSuperAdminOrganizations } from '../hooks/useSuperAdminOrganizations'

const TAB_ID_PREFIX = 'admin'

type AdminDashboardProps = {
  admin: ReturnType<typeof useQuickBooksAdmin>
}

/**
 * Authenticated admin workspace with preferences and integrations tabs.
 * @param props QuickBooks admin hook state and handlers.
 * @returns Tabbed dashboard content.
 */
export function AdminDashboard({ admin }: AdminDashboardProps) {
  const { t } = useLocale()
  const userId = admin.user!.id
  const [activeTab, setActiveTab] = useAdminActiveTab(userId)
  const isAdministrator = admin.user?.is_admin === true
  const isSuperAdministrator = admin.user?.is_super_admin === true
  const isTenantAdministrator = isAdministrator && !isSuperAdministrator

  useEffect(() => {
    try {
      sessionStorage.removeItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY)
    } catch {
      // Ignore privacy-mode failures.
    }
  }, [])

  const tabs = useMemo(() => {
    const items: { id: AdminTab; label: string }[] = [
      { id: 'preferences', label: t('admin.tabPreferences') },
    ]

    if (isTenantAdministrator) {
      items.push({ id: 'integrations', label: t('admin.tabIntegrations') })
    }

    if (isSuperAdministrator) {
      items.push({ id: 'clients', label: t('admin.tabClients') })
    }

    return items
  }, [isTenantAdministrator, isSuperAdministrator, t])

  const activeTabId = tabs.some((tab) => tab.id === activeTab) ? activeTab : 'preferences'

  useEffect(() => {
    if (activeTab !== activeTabId) {
      setActiveTab(activeTabId)
    }
  }, [activeTab, activeTabId, setActiveTab])

  const {
    focusIntegrationsTab,
    clearFocusIntegrationsTab,
  } = admin

  useEffect(() => {
    if (focusIntegrationsTab && isTenantAdministrator) {
      setActiveTab('integrations')
      clearFocusIntegrationsTab()
    }
  }, [focusIntegrationsTab, clearFocusIntegrationsTab, isTenantAdministrator, setActiveTab])

  const provisioning = useTimesheetProvisioning({
    status: admin.status,
    isAdministrator: isTenantAdministrator,
    integrationsTabActive: activeTabId === 'integrations',
    onError: admin.showError,
    onSuccess: admin.showSuccess,
  })

  const clientOrganizations = useSuperAdminOrganizations({
    enabled: isSuperAdministrator && activeTabId === 'clients',
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
      ) : activeTabId === 'clients' ? (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'clients')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-clients`}
        >
          <SuperAdminOrganizationsPanel clients={clientOrganizations} />
        </div>
      ) : (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'integrations')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-integrations`}
          className="space-y-6"
        >
          <QuickBooksConnectionPanel
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
            removing={provisioning.removing}
            removingUserId={provisioning.removingUserId}
            onEmployeeChange={provisioning.onEmployeeChange}
            onEmployeeDropdownOpen={provisioning.onEmployeeDropdownOpen}
            onEmployeeDropdownClose={provisioning.onEmployeeDropdownClose}
            onSubmit={provisioning.onCreateTimesheetUser}
            onRemoveTimesheetUser={provisioning.onRemoveTimesheetUser}
            onClientAssignmentsSaved={provisioning.onClientAssignmentsSaved}
            onError={admin.showError}
            onSuccess={admin.showSuccess}
          />
        </div>
      )}
    </div>
  )
}
