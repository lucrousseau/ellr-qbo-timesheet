/**
 * @file Tests for API password validation message mapping.
 */

import { describe, expect, it } from 'vitest'
import {
  isPasswordUncompromisedValidationMessage,
  passwordPolicyErrorCodeFromApiMessage,
} from './apiMessages'

describe('passwordPolicyErrorCodeFromApiMessage', () => {
  it('maps english uncompromised validation copy', () => {
    expect(
      passwordPolicyErrorCodeFromApiMessage(
        'The given password has appeared in a data leak. Please choose a different password.',
      ),
    ).toBe('password_uncompromised')
  })

  it('maps french uncompromised validation copy', () => {
    expect(
      passwordPolicyErrorCodeFromApiMessage(
        'La valeur du champ password est apparue dans une fuite de données. Veuillez choisir une valeur différente.',
      ),
    ).toBe('password_uncompromised')
  })

  it('returns null for unrelated validation messages', () => {
    expect(passwordPolicyErrorCodeFromApiMessage('The password field is required.')).toBeNull()
  })

  it('detects uncompromised messages with a helper', () => {
    expect(isPasswordUncompromisedValidationMessage('appeared in a data leak')).toBe(true)
    expect(isPasswordUncompromisedValidationMessage('too short')).toBe(false)
  })
})
