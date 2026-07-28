/**
 * @file Loads the shared password policy JSON configuration.
 */

import policyJson from '../password-policy.json'
import type { PasswordPolicy } from './types'

/**
 * Returns the shared password policy configuration.
 * @returns Password policy values from password-policy.json.
 */
export function loadPasswordPolicy(): PasswordPolicy {
  return policyJson as PasswordPolicy
}

const policy = loadPasswordPolicy()

/**
 * Strong password that satisfies the shared policy (tests and dev seeds).
 */
export const VALID_TEST_PASSWORD = policy.testPasswords?.primary ?? 'EllrT3st!2026'

/**
 * Alternate strong password for change-password scenarios in tests.
 */
export const VALID_TEST_PASSWORD_ALT = policy.testPasswords?.alternate ?? 'EllrNew!2026'
