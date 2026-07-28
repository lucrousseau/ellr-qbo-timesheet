/**
 * @file Maps shared password policy error codes to localized UI messages.
 */

import {
  getPasswordRequirementMessageKeys,
  loadPasswordPolicy,
  passwordPolicyErrorCodeFromApiMessage,
  type PasswordPolicyErrorCode,
} from '@ellr/password-policy'
import type { UserLocale } from '@ellr/api-client'
import { translate } from '../i18n/translate'

/**
 * Returns the localized label for a password policy error code.
 * @param locale Active user locale.
 * @param code Stable password validation error code.
 * @returns User-facing error message.
 */
export function translatePasswordPolicyError(
  locale: UserLocale,
  code: PasswordPolicyErrorCode,
): string {
  const policy = loadPasswordPolicy()

  return translate(locale, `auth.passwordPolicy.errors.${code}`, {
    minLength: policy.minLength,
  })
}

/**
 * Returns localized labels for multiple password policy error codes.
 * @param locale Active user locale.
 * @param codes Stable password validation error codes.
 * @returns Combined user-facing error message.
 */
export function translatePasswordPolicyErrors(
  locale: UserLocale,
  codes: PasswordPolicyErrorCode[],
): string {
  return codes.map((code) => translatePasswordPolicyError(locale, code)).join(' ')
}

/**
 * Maps a backend password validation message to a localized UI label when possible.
 * @param locale Active user locale.
 * @param message Laravel validation message from a 422 response.
 * @returns Localized password policy message, or `null` when unmatched.
 */
export function translateApiPasswordValidationMessage(
  locale: UserLocale,
  message: string,
): string | null {
  const code = passwordPolicyErrorCodeFromApiMessage(message)

  return code ? translatePasswordPolicyError(locale, code) : null
}

/**
 * Returns localized requirement hints for the shared password policy.
 * @param locale Active user locale.
 * @returns Requirement labels for display beside password fields.
 */
export function getPasswordRequirementLabels(locale: UserLocale): string[] {
  const policy = loadPasswordPolicy()

  return getPasswordRequirementMessageKeys(policy).map((key) => {
    const segment = key.split('.').pop() ?? key

    return translate(locale, `auth.passwordPolicy.requirements.${segment}`, {
      minLength: policy.minLength,
    })
  })
}
