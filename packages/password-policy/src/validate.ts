/**
 * @file Client-side password validation against the shared policy.
 */

import { loadPasswordPolicy } from './policy'
import type { PasswordPolicy, PasswordPolicyErrorCode } from './types'

const UPPERCASE_PATTERN = /[A-Z]/
const LOWERCASE_PATTERN = /[a-z]/
const NUMBER_PATTERN = /\d/
const SYMBOL_PATTERN = /[^A-Za-z0-9]/

/**
 * Validates a password against the shared policy (excluding breach checks).
 * @param password Candidate password.
 * @param policy Optional policy override (defaults to shared JSON).
 * @returns Stable error codes for each failed rule.
 */
export function validatePassword(
  password: string,
  policy: PasswordPolicy = loadPasswordPolicy(),
): PasswordPolicyErrorCode[] {
  const errors: PasswordPolicyErrorCode[] = []

  if (password.length < policy.minLength) {
    errors.push('password_too_short')
  }

  if (policy.requireUppercase && !UPPERCASE_PATTERN.test(password)) {
    errors.push('password_missing_uppercase')
  }

  if (policy.requireLowercase && !LOWERCASE_PATTERN.test(password)) {
    errors.push('password_missing_lowercase')
  }

  if (policy.requireNumbers && !NUMBER_PATTERN.test(password)) {
    errors.push('password_missing_number')
  }

  if (policy.requireSymbols && !SYMBOL_PATTERN.test(password)) {
    errors.push('password_missing_symbol')
  }

  return errors
}

/**
 * Returns whether password and confirmation match.
 * @param password New password value.
 * @param confirmation Confirmation field value.
 * @returns `true` when both values are identical.
 */
export function passwordsMatch(password: string, confirmation: string): boolean {
  return password === confirmation
}

/**
 * Validates password and confirmation together.
 * @param password New password value.
 * @param confirmation Confirmation field value.
 * @param policy Optional policy override.
 * @returns Stable error codes for policy and confirmation failures.
 */
export function validatePasswordWithConfirmation(
  password: string,
  confirmation: string,
  policy: PasswordPolicy = loadPasswordPolicy(),
): PasswordPolicyErrorCode[] {
  const errors = validatePassword(password, policy)

  if (!passwordsMatch(password, confirmation)) {
    errors.push('password_confirmation_mismatch')
  }

  return errors
}

/**
 * Returns i18n keys describing active password requirements for UI hints.
 * @param policy Optional policy override.
 * @returns Dotted message keys under `auth.passwordPolicy.requirements.*`.
 */
export function getPasswordRequirementMessageKeys(
  policy: PasswordPolicy = loadPasswordPolicy(),
): string[] {
  const keys = [`auth.passwordPolicy.requirements.minLength`]

  if (policy.requireUppercase) {
    keys.push('auth.passwordPolicy.requirements.uppercase')
  }

  if (policy.requireLowercase) {
    keys.push('auth.passwordPolicy.requirements.lowercase')
  }

  if (policy.requireNumbers) {
    keys.push('auth.passwordPolicy.requirements.number')
  }

  if (policy.requireSymbols) {
    keys.push('auth.passwordPolicy.requirements.symbol')
  }

  if (policy.uncompromised) {
    keys.push('auth.passwordPolicy.requirements.uncompromised')
  }

  return keys
}
