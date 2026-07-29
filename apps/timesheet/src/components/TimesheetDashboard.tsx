/**
 * @file Tabbed timesheet dashboard with timer and preferences sections.
 */

import {
  Alert,
  AppShell,
  TabNav,
  tabPanelId,
  UserPreferencesPanel,
  useLocale,
  useSessionStorageState,
} from '@ellr/ui'
import { useMemo } from 'react'
import { isTimesheetTab, timesheetActiveTabStorageKey, type TimesheetTab } from '../timesheetTabStorage'
import type { useTimesheetAuth } from '../hooks/useTimesheetAuth'
import type { useTimeTracker } from '../hooks/useTimeTracker'
import { EmailVerificationBanner } from './EmailVerificationBanner'
import { QboEmployeeWarning } from './QboEmployeeWarning'
import { TimeTrackerPanel } from './TimeTrackerPanel'

const TAB_ID_PREFIX = 'timesheet'

type TimesheetDashboardProps = {
  auth: ReturnType<typeof useTimesheetAuth>
  tracker: ReturnType<typeof useTimeTracker>
}

/**
 * Authenticated timesheet shell with timer and preferences tabs.
 * @param props Auth and timer hook state from the app root.
 * @returns Tabbed timesheet experience inside AppShell.
 */
export function TimesheetDashboard({ auth, tracker }: TimesheetDashboardProps) {
  const { t } = useLocale()
  const user = auth.user!
  const [activeTab, setActiveTab] = useSessionStorageState<TimesheetTab>(
    timesheetActiveTabStorageKey(user.id),
    'timer',
    { isValid: isTimesheetTab },
  )

  const tabs = useMemo(
    () => [
      { id: 'timer' as const, label: t('timesheet.tabTimer') },
      { id: 'preferences' as const, label: t('timesheet.tabPreferences') },
    ],
    [t],
  )

  const activeTabId = tabs.some((tab) => tab.id === activeTab) ? activeTab : 'timer'

  const onLogout = async () => {
    await auth.handleLogout()
  }

  return (
    <AppShell
      title={t('timesheet.appTitle')}
      userEmail={user.email}
      onLogout={onLogout}
      loggingOut={auth.loggingOut}
    >
      {auth.bootstrapError && (
        <div className="mb-4">
          <Alert variant="error">{auth.bootstrapError}</Alert>
        </div>
      )}
      {auth.preferenceNotice && (
        <div className="mb-4">
          <Alert variant={auth.preferenceNoticeVariant === 'error' ? 'error' : 'success'}>
            {auth.preferenceNotice}
          </Alert>
        </div>
      )}

      <TabNav
        items={tabs}
        activeId={activeTabId}
        onChange={setActiveTab}
        ariaLabel={t('timesheet.tabsLabel')}
        idPrefix={TAB_ID_PREFIX}
      />

      {activeTabId === 'timer' ? (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'timer')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-timer`}
          className="mt-6 space-y-6"
        >
          {auth.showEmailVerification(user) && (
            <EmailVerificationBanner
              message={auth.verificationMessage}
              messageVariant={auth.verificationMessageVariant}
              sending={auth.verificationSending}
              onResend={auth.handleResendVerification}
            />
          )}
          {!user.qbo_employee_ref ? (
            <QboEmployeeWarning />
          ) : auth.showEmailVerification(user) ? null : (
            <TimeTrackerPanel
              loading={tracker.loading}
              headerLabel={tracker.headerLabel}
              customer={tracker.state.customer}
              project={tracker.state.project}
              service={tracker.state.service}
              description={tracker.state.description}
              isBillable={tracker.state.isBillable}
              elapsedSeconds={tracker.elapsedSeconds}
              isRunning={tracker.isRunning}
              logging={tracker.logging}
              discarding={tracker.discarding}
              message={tracker.message}
              customersSelect={tracker.customersSelect}
              projectsSelect={tracker.projectsSelect}
              servicesSelect={tracker.servicesSelect}
              showCustomerPicker={tracker.showCustomerPicker}
              showProjectPicker={tracker.showProjectPicker}
              showServicePicker={tracker.showServicePicker}
              onCustomerChange={tracker.onCustomerChange}
              onProjectChange={tracker.onProjectChange}
              onServiceChange={tracker.onServiceChange}
              onDescriptionChange={tracker.onDescriptionChange}
              onDescriptionBlur={tracker.onDescriptionBlur}
              onBillableChange={tracker.onBillableChange}
              onToggleTimer={tracker.onToggleTimer}
              onElapsedChange={tracker.onElapsedChange}
              onLogTime={tracker.onLogTime}
              onDiscard={tracker.onDiscard}
            />
          )}
        </div>
      ) : (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'preferences')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-preferences`}
          className="mt-6"
        >
          <UserPreferencesPanel
            locale={auth.preferenceLocale}
            saving={auth.savingLocale}
            onLocaleChange={auth.setPreferenceLocale}
            onSave={auth.saveLocale}
          />
        </div>
      )}
    </AppShell>
  )
}
