/**
 * @file Public exports for the shared Laravel API HTTP client.
 */

export {
  createTimesheetUser,
  fetchQboEmployees,
  fetchTimesheetUsers,
  type CreateTimesheetUserPayload,
  type QboEmployeeOption,
} from './admin'
export { ApiError, API_URL, apiFetch, ensureCsrfCookie, getApiErrorMessage, resetCsrfStateForTests } from './api'
export { fetchAppConfig, type AppConfig } from './appConfig'
export { getLocalizedApiErrorMessage } from './errorMessages'
export { normalizeUserLocale, SUPPORTED_LOCALES, type UserLocale } from './locale'
export {
  changePassword,
  fetchCurrentUser,
  login,
  logout,
  requestPasswordReset,
  resendVerificationEmail,
  resetPassword,
  updateQboEmployee,
  updateUserLocale,
  updateUserQboEmployee,
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
  quickBooksOAuthErrorMessage,
  type QuickBooksConnectResponse,
  type QuickBooksStatus,
} from './quickbooks'
export {
  createTimeActivity,
  listTimeActivities,
  type ListTimeActivitiesParams,
  type TimeActivity,
  type TimeActivityListMeta,
  type TimeActivityListResponse,
  type TimeActivityPayload,
} from './timesheet'
