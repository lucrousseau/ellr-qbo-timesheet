/**
 * @file Integrations tab content for QuickBooks connection, projects, and provisioning.
 */

import { LoadingScreen } from '@ellr/ui'
import { lazy, Suspense } from 'react'
import type { useAdminQboEmployeeMapping } from '../hooks/useAdminQboEmployeeMapping'
import type { useCreateQboProject } from '../hooks/useCreateQboProject'
import type { useQuickBooksAdmin } from '../hooks/useQuickBooksAdmin'
import type { useTimesheetProvisioning } from '../hooks/useTimesheetProvisioning'
import { QuickBooksConnectionPanel } from './QuickBooksConnectionPanel'

const AdminQboEmployeeMappingPanel = lazy(async () => {
  const module = await import('./AdminQboEmployeeMappingPanel')
  return { default: module.AdminQboEmployeeMappingPanel }
})

const CreateQboProjectPanel = lazy(async () => {
  const module = await import('./CreateQboProjectPanel')
  return { default: module.CreateQboProjectPanel }
})

const TimesheetUserProvisioningPanel = lazy(async () => {
  const module = await import('./TimesheetUserProvisioningPanel')
  return { default: module.TimesheetUserProvisioningPanel }
})

type IntegrationsTabContentProps = {
  admin: ReturnType<typeof useQuickBooksAdmin>
  adminMapping: ReturnType<typeof useAdminQboEmployeeMapping>
  projectCreate: ReturnType<typeof useCreateQboProject>
  provisioning: ReturnType<typeof useTimesheetProvisioning>
}

/**
 * QuickBooks integrations panels shown on the admin Integrations tab.
 * @param props Admin, self-mapping, project create, and provisioning hook state.
 * @returns Connection, self-mapping, project create, and timesheet access panels.
 */
export function IntegrationsTabContent({
  admin,
  adminMapping,
  projectCreate,
  provisioning,
}: IntegrationsTabContentProps) {
  return (
    <div className="space-y-6">
      <QuickBooksConnectionPanel
        status={admin.status}
        connecting={admin.connecting}
        disconnecting={admin.disconnecting}
        onConnect={admin.connectQuickBooksFlow}
        onDisconnect={admin.disconnectQuickBooksFlow}
      />
      <Suspense fallback={<LoadingScreen variant="inline" />}>
        <AdminQboEmployeeMappingPanel
          connected={admin.status?.connected === true}
          employees={adminMapping.employees}
          loadingEmployees={adminMapping.loadingEmployees}
          employeesLoaded={adminMapping.employeesLoaded}
          selectedEmployee={adminMapping.selectedEmployee}
          currentRef={adminMapping.currentRef}
          mappedEmployeeLabel={adminMapping.mappedEmployeeLabel}
          saving={adminMapping.saving}
          onEmployeeChange={adminMapping.onEmployeeChange}
          onEmployeeDropdownOpen={adminMapping.onEmployeeDropdownOpen}
          onEmployeeDropdownClose={adminMapping.onEmployeeDropdownClose}
          onSubmit={adminMapping.onSaveMapping}
        />
        <CreateQboProjectPanel
          connected={admin.status?.connected === true}
          customers={projectCreate.customers}
          loadingCustomers={projectCreate.loadingCustomers}
          customersLoaded={projectCreate.customersLoaded}
          selectedCustomer={projectCreate.selectedCustomer}
          displayName={projectCreate.displayName}
          creating={projectCreate.creating}
          onCustomerChange={projectCreate.onCustomerChange}
          onCustomerDropdownOpen={projectCreate.onCustomerDropdownOpen}
          onCustomerDropdownClose={projectCreate.onCustomerDropdownClose}
          onDisplayNameChange={projectCreate.onDisplayNameChange}
          onSubmit={projectCreate.onCreateProject}
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
          assignSupervisor={provisioning.assignSupervisor}
          assigningSupervisor={provisioning.assigningSupervisor}
          onError={admin.showError}
          onSuccess={admin.showSuccess}
        />
      </Suspense>
    </div>
  )
}
