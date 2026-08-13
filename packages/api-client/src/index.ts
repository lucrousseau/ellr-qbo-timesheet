/**
 * @file Public exports for the shared Laravel API HTTP client.
 */

export {
  createSuperAdminOrganization,
  deleteSuperAdminOrganization,
  fetchSuperAdminOrganizations,
  updateSuperAdminOrganization,
  type CreateSuperAdminOrganizationPayload,
  type SuperAdminOrganization,
  type SuperAdminOrganizationAdministrator,
  type UpdateSuperAdminOrganizationPayload,
} from './superAdmin'
export {
  createQboProject,
  createTimesheetUser,
  deleteTimesheetUser,
  fetchAdminQboCustomers,
  fetchQboEmployees,
  fetchTimesheetUserCustomers,
  fetchTimesheetUsers,
  listAdminUserTimeActivities,
  syncTimesheetUserCustomers,
  updateAdminUserTimeActivity,
  type CreateQboProjectPayload,
  type CreateTimesheetUserPayload,
  type QboCustomerOption,
  type QboEmployeeOption,
  type TimesheetUserCustomerAccess,
} from './admin'
export { ApiError, API_URL, apiFetch, ensureCsrfCookie, resetCsrfStateForTests } from './api'
export {
  API_ERROR_MESSAGE_KEYS,
  resolveApiError,
  type ApiErrorMessageKey,
  type ApiErrorResolution,
} from './apiErrorResolution'
export { fetchAppConfig, DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS, type AppConfig } from './appConfig'
export { normalizeQboRef, qboRefsMatch } from './qboRef'
export { normalizeUserLocale, SUPPORTED_LOCALES, type UserLocale } from './locale'
export {
  COMMON_TIMEZONES,
  formatTimezoneLabel,
  normalizeUserTimezone,
  resolveEffectiveTimezone,
  timezonePreferenceForApi,
  timezonePreferenceOptions,
} from './timezone'
export {
  DEFAULT_USER_LEVEL,
  normalizeUserLevel,
  USER_LEVELS,
  type UserLevel,
} from './userLevel'
export {
  changeEmail,
  changePassword,
  fetchCurrentUser,
  login,
  logout,
  register,
  requestPasswordReset,
  resendVerificationEmail,
  resetPassword,
  updateQboEmployee,
  updateUserLocale,
  updateUserPreferences,
  updateUserTimezone,
  updateUserQboEmployee,
  type Organization,
  type RegisterPayload,
  type User,
} from './auth'
export {
  isEmailUnverified,
  hasValidPasswordResetInvite,
  isResetPasswordRoute,
  parseEmailVerificationCallback,
  parseResetPasswordParams,
  shouldBlockUnverifiedUser,
  type EmailVerificationCallback,
} from './authRecovery'
export {
  connectQuickBooks,
  disconnectQuickBooks,
  fetchQuickBooksStatus,
  parseQuickBooksOAuthCallback,
  type QuickBooksConnectResponse,
  type QuickBooksStatus,
} from './quickbooks'
export {
  formatTimeActivityDuration,
  fromDateTimeLocalValue,
  parseTimeActivityRow,
  toDateTimeLocalValue,
  type TimeActivityRow,
  type TimeActivityUpdatePayload,
} from './timeActivityFormat'
export {
  approveTimeEntry,
  createTimeEntry,
  deleteTimeEntry,
  listPendingTimeEntryApprovals,
  listTimeEntries,
  rejectTimeEntry,
  resolveTimeEntryId,
  updateAdminUserSupervisor,
  updateTimeEntry,
  type ListTimeEntriesParams,
  type TimeEntry,
  type TimeEntryListMeta,
  type TimeEntryListResponse,
  type TimeEntryPayload,
  type TimeEntryStatus,
  type TimeEntryUpdatePayload,
} from './timeEntries'
export { parseTimeEntryRow } from './timeEntryFormat'
export {
  createTimeActivity,
  discardTimeTracker,
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  fetchTimeTracker,
  listTimeActivities,
  logTimeTracker,
  updateTimeActivity,
  updateTimeTracker,
  type ListTimeActivitiesParams,
  type QboPickerOption,
  type TimeActivity,
  type TimeActivityListMeta,
  type TimeActivityListResponse,
  type TimeActivityPayload,
  type TimeTrackerPayload,
  type TimeTrackerSession,
} from './timesheet'
