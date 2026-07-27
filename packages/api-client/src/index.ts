/**
 * @file Public exports for the shared Laravel API HTTP client.
 */

export { ApiError, API_URL, apiFetch, ensureCsrfCookie, getApiErrorMessage, resetCsrfStateForTests } from './api'
export { fetchAppConfig, type AppConfig } from './appConfig'
export {
  fetchCurrentUser,
  login,
  logout,
  requestPasswordReset,
  resendVerificationEmail,
  resetPassword,
  updateQboEmployee,
  updateUserQboEmployee,
  type User,
} from './auth'
export {
  emailVerificationMessage,
  isEmailUnverified,
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
export { createTimeActivity, type TimeActivity, type TimeActivityPayload } from './timesheet'
