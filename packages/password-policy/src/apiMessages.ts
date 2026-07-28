/**
 * @file Maps API validation messages to shared password policy error codes.
 */

import type { PasswordPolicyErrorCode } from './types'

const UNCOMPROMISED_PATTERNS = [
  /data leak/i,
  /fuite de données/i,
  /uncompromised/i,
  /appeared in a data leak/i,
  /apparue dans une fuite/i,
]

/**
 * Detects whether an API validation message refers to a breached password.
 * @param message Laravel validation message from a 422 response.
 * @returns `true` when the message matches uncompromised validation copy.
 */
export function isPasswordUncompromisedValidationMessage(message: string): boolean {
  return UNCOMPROMISED_PATTERNS.some((pattern) => pattern.test(message))
}

/**
 * Maps a backend password validation message to a stable client error code.
 * @param message Laravel validation message from a 422 response.
 * @returns Matching password policy code, if any.
 */
export function passwordPolicyErrorCodeFromApiMessage(
  message: string,
): PasswordPolicyErrorCode | null {
  if (isPasswordUncompromisedValidationMessage(message)) {
    return 'password_uncompromised'
  }

  return null
}
