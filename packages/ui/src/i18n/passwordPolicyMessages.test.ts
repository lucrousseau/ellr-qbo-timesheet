/**
 * @file Tests for password policy UI message helpers.
 */

import { describe, expect, it } from 'vitest'
import {
  getPasswordRequirementLabels,
  translateApiPasswordValidationMessage,
  translatePasswordPolicyErrors,
} from './passwordPolicyMessages'

describe('passwordPolicyMessages', () => {
  it('translates multiple password policy errors', () => {
    const message = translatePasswordPolicyErrors('en', [
      'password_too_short',
      'password_missing_symbol',
    ])

    expect(message).toContain('at least 12 characters')
    expect(message).toContain('symbol')
  })

  it('maps uncompromised api validation copy to localized ui text', () => {
    const message = translateApiPasswordValidationMessage(
      'en',
      'The given password has appeared in a data leak. Please choose a different password.',
    )

    expect(message).toContain('data breach')
  })

  it('lists password requirements from the shared policy', () => {
    const labels = getPasswordRequirementLabels('en')

    expect(labels.some((label) => label.includes('12'))).toBe(true)
    expect(labels.some((label) => label.toLowerCase().includes('symbol'))).toBe(true)
  })
})
