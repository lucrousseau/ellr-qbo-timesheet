/**
 * @file Tests for shared password policy validation.
 */

import { describe, expect, it } from 'vitest'
import testPasswords from '../test-passwords.json'
import { loadPasswordPolicy } from './policy'
import {
  getPasswordRequirementMessageKeys,
  passwordsMatch,
  validatePassword,
  validatePasswordWithConfirmation,
} from './validate'

describe('validatePassword', () => {
  const policy = loadPasswordPolicy()

  it('accepts a password that meets every rule', () => {
    expect(validatePassword(testPasswords.primary, policy)).toEqual([])
  })

  it('rejects passwords that are too short', () => {
    expect(validatePassword('Short1!', policy)).toContain('password_too_short')
  })

  it('rejects passwords missing required character classes', () => {
    expect(validatePassword('alllowercase1!', policy)).toContain('password_missing_uppercase')
    expect(validatePassword('ALLUPPERCASE1!', policy)).toContain('password_missing_lowercase')
    expect(validatePassword('NoNumbersHere!', policy)).toContain('password_missing_number')
    expect(validatePassword('NoSymbols12345', policy)).toContain('password_missing_symbol')
  })

  it('flags confirmation mismatches', () => {
    expect(
      validatePasswordWithConfirmation(testPasswords.primary, 'Different!2026', policy),
    ).toContain('password_confirmation_mismatch')
  })

  it('reports passwordsMatch correctly', () => {
    expect(passwordsMatch('abc', 'abc')).toBe(true)
    expect(passwordsMatch('abc', 'def')).toBe(false)
  })

  it('lists requirement message keys from the policy', () => {
    const keys = getPasswordRequirementMessageKeys(policy)

    expect(keys).toContain('auth.passwordPolicy.requirements.minLength')
    expect(keys).toContain('auth.passwordPolicy.requirements.uncompromised')
  })
})
