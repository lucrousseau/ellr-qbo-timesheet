/**
 * @file Public exports for shared React UI components and hooks.
 */

export { Button } from './components/Button'
export { CheckboxField } from './components/CheckboxField'
export { AppDialog } from './components/AppDialog'
export { ConfirmDialog } from './components/ConfirmDialog'
export { TextAreaField } from './components/TextAreaField'
export { LazySearchCombobox } from './components/LazySearchCombobox'
export { StaticSelect } from './components/StaticSelect'
export { TextField } from './components/TextField'
export { MaskedInput } from './components/MaskedInput'
export {
  capDurationMaskValue,
  createDurationHoursMinutesMask,
  DEFAULT_DURATION_MAX_SECONDS,
  durationHoursMinutesMask,
} from './masks/durationMask'
export { Alert } from './components/Alert'
export { AppShell } from './components/AppShell'
export { AuthPageLayout } from './components/AuthPageLayout'
export { SupportContactLink } from './components/SupportContactLink'
export {
  SUPPORT_EMAIL_EN,
  SUPPORT_EMAIL_FR,
  supportEmailForLocale,
  supportMailtoHref,
} from './supportContact'
export { EllrLogoGraphic } from './components/EllrLogoGraphic'
export { EllrLogoMark } from './components/EllrLogoMark'
export { PasswordPolicyRequirements } from './components/PasswordPolicyRequirements'
export { ChangePasswordPanel } from './components/ChangePasswordPanel'
export { ForgotPasswordForm } from './components/ForgotPasswordForm'
export { LoadingScreen } from './components/LoadingScreen'
export { BootstrapUnavailableScreen } from './components/BootstrapUnavailableScreen'
export { LoginForm } from './components/LoginForm'
export { ResetPasswordForm } from './components/ResetPasswordForm'
export { TabNav, tabButtonId, tabPanelId, type TabNavItem } from './components/TabNav'
export { TimeActivityEntriesPanel, type TimeActivityEntriesPanelProps } from './components/TimeActivityEntriesPanel'
export { TimeEntryApprovalList } from './components/TimeEntryApprovalList'
export {
  TimeEntrySyncGroupDialog,
  type TimeEntrySyncGroupDialogProps,
} from './components/TimeEntrySyncGroupDialog'
export { useTimeEntrySyncGroupViewer } from './hooks/useTimeEntrySyncGroupViewer'
export { UserPreferencesPanel } from './components/UserPreferencesPanel'
export { LocaleProvider, useLocale } from './i18n/LocaleProvider'
export {
  getPasswordRequirementLabels,
  translateApiPasswordValidationMessage,
  translatePasswordPolicyErrors,
} from './i18n/passwordPolicyMessages'
export { getApiErrorMessage, translateApiError } from './i18n/apiErrorMessages'
export { useGuardedAction } from './hooks/useGuardedAction'
export { useLazyApiSelect } from './hooks/useLazyApiSelect'
export { useAuth } from './hooks/useAuth'
export { useChangePassword } from './hooks/useChangePassword'
export { useSyncUserLocale } from './hooks/useSyncUserLocale'
export { useUserLocalePreferences } from './hooks/useUserLocalePreferences'
export { useUserPreferences } from './hooks/useUserPreferences'
export { useTimeEntryApprovals } from './hooks/useTimeEntryApprovals'
export type { UserLocale } from '@ellr/api-client'
export { usePasswordResetInviteGate } from './hooks/usePasswordResetInviteGate'
export { usePasswordRecovery, type AuthClient, type AuthScreen } from './hooks/usePasswordRecovery'
export { useFlashMessage, type FlashMessage } from './hooks/useFlashMessage'
export { useSessionStorageState } from './hooks/useSessionStorageState'
export {
  alertClasses,
  cardClass,
  inputClass,
  nestedPanelClass,
  pageMainClass,
  pageSubtitleClass,
  pageTitleClass,
  primaryButtonClass,
  secondaryButtonClass,
  sectionHelpClass,
  sectionTitleClass,
  sectionTitleCompactClass,
} from './styles/tokens'
export { fieldLabelClass, checkboxClass, checkboxLabelClass } from './styles/formTokens'
export {
  selectOptionClass,
  selectOptionsPanelClass,
  selectSearchInputClass,
  selectTriggerClass,
} from './styles/selectTokens'
