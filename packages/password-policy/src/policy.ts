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

