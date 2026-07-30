/**
 * @file Tabbed timesheet dashboard with timer and preferences sections.
 */

import {
  Alert,
  AppShell,
  LoadingScreen,
  TabNav,
  tabPanelId,
  TimeActivityEntriesPanel,
  UserPreferencesPanel,
  useLocale,
  useSessionStorageState,
} from '@ellr/ui'
import { resolveEffectiveTimezone } from '@ellr/api-client'
import { useMemo } from 'react'
import { isTimesheetTab, timesheetActiveTabStorageKey, type TimesheetTab } from '../timesheetTabStorage'
import type { useTimesheetAuth } from '../hooks/useTimesheetAuth'
import type { useTimeTracker } from '../hooks/useTimeTracker'
import { useRecentTimeActivities } from '../hooks/useRecentTimeActivities'
import { EmailVerificationBanner } from './EmailVerificationBanner'
import { AssignedClientsWarning } from './AssignedClientsWarning'
import { CustomerLoadError } from './CustomerLoadError'
import { QboEmployeeWarning } from './QboEmployeeWarning'
import { TimeTrackerPanel } from './TimeTrackerPanel'
import { TimesheetTimeEntryApprovalsPanel } from './TimesheetTimeEntryApprovalsPanel'

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

  const tabs = useMemo(() => {
    const items: { id: TimesheetTab; label: string }[] = [
      { id: 'timer', label: t('timesheet.tabTimer') },
    ]

    if (user.can_review_time_entries) {
      items.push({ id: 'approvals', label: t('timesheet.tabApprovals') })
    }

    items.push({ id: 'preferences', label: t('timesheet.tabPreferences') })

    return items
  }, [t, user.can_review_time_entries])

  const activeTabId = tabs.some((tab) => tab.id === activeTab) ? activeTab : 'timer'
  const displayTimezone = resolveEffectiveTimezone(user)
  const canUseTimerTab =
    Boolean(user.qbo_employee_ref) && !auth.showEmailVerification(user)
  const showTimeTracking = canUseTimerTab && (tracker.loading || tracker.canTrackTime)
  const recentEntries = useRecentTimeActivities({
    enabled: showTimeTracking && !tracker.loading && tracker.canTrackTime,
    refreshToken: tracker.entriesRefreshToken,
  })

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
          ) : auth.showEmailVerification(user) ? null : tracker.loading || tracker.customersStatus === 'unknown' ? (
            <LoadingScreen />
          ) : !tracker.canTrackTime && tracker.customersStatus === 'empty' ? (
            <AssignedClientsWarning
              allCustomersAccess={user.all_customers_access === true}
              hasDraftSession={tracker.hasDraftSession}
              discarding={tracker.discarding}
              onDiscard={() => void tracker.onDiscard()}
            />
          ) : !tracker.canTrackTime && tracker.customersStatus === 'error' ? (
            <CustomerLoadError
              hasDraftSession={tracker.hasDraftSession}
              discarding={tracker.discarding}
              onDiscard={() => void tracker.onDiscard()}
            />
          ) : (
            <>
              <TimeTrackerPanel
                loading={tracker.loading}
                headerLabel={tracker.headerLabel}
                customer={tracker.state.customer}
                project={tracker.state.project}
                service={tracker.state.service}
                description={tracker.state.description}
                isBillable={tracker.state.isBillable}
                elapsedSeconds={tracker.elapsedSeconds}
                maxAccumulatedSeconds={tracker.maxAccumulatedSeconds}
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
                commitElapsedSeconds={tracker.commitElapsedSeconds}
                onLogTime={tracker.onLogTime}
                onDiscard={tracker.onDiscard}
              />
              <TimeActivityEntriesPanel
                title={t('timesheet.recentEntriesTitle')}
                entries={recentEntries.entries}
                loading={recentEntries.loading}
                error={recentEntries.error}
                hasMore={recentEntries.hasMore}
                loadingMore={recentEntries.loadingMore}
                onLoadMore={recentEntries.loadMore}
                showApprovalStatus
                displayTimezone={displayTimezone}
              />
            </>
          )}
        </div>
      ) : activeTabId === 'approvals' ? (
        <div
          id={tabPanelId(TAB_ID_PREFIX, 'approvals')}
          role="tabpanel"
          aria-labelledby={`${TAB_ID_PREFIX}-tab-approvals`}
          className="mt-6"
        >
          <TimesheetTimeEntryApprovalsPanel
            enabled={user.can_review_time_entries === true}
            useAdminRoutes={user.is_admin === true}
            onSuccess={(message) => auth.setPreferenceNotice(message, 'success')}
            onError={(message) => auth.setPreferenceNotice(message, 'error')}
          />
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
            timezone={auth.preferenceTimezone}
            companyTimezone={user.organization?.company_timezone}
            saving={auth.savingPreferences}
            onLocaleChange={auth.setPreferenceLocale}
            onTimezoneChange={auth.setPreferenceTimezone}
            onSave={auth.savePreferences}
          />
        </div>
      )}
    </AppShell>
  )
}
