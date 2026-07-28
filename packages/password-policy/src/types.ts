/**
 * @file Shared password policy types aligned with password-policy.json.
 */

/** Stable error codes returned by client-side password validation. */
export type PasswordPolicyErrorCode =
  | 'password_too_short'
  | 'password_missing_uppercase'
  | 'password_missing_lowercase'
  | 'password_missing_number'
  | 'password_missing_symbol'
  | 'password_confirmation_mismatch'
  | 'password_uncompromised'

/** Password strength rules shared by backend and frontend. */
export type PasswordPolicy = {
  minLength: number
  requireUppercase: boolean
  requireLowercase: boolean
  requireNumbers: boolean
  requireSymbols: boolean
  uncompromised: boolean
}
