/**
 * @file Tests for UI message catalog translation.
 */

import { describe, expect, it } from 'vitest'
import { translate } from './translate'

describe('translate', () => {
  it('returns english catalog strings', () => {
    expect(translate('en', 'common.signIn')).toBe('Sign in')
  })

  it('returns french catalog strings', () => {
    expect(translate('fr', 'timesheet.appTitle')).toBe('Feuille de temps')
  })

  it('interpolates template parameters', () => {
    expect(translate('en', 'common.signedInAs', { email: 'user@example.com' })).toBe(
      'Signed in as user@example.com',
    )
  })

  it('returns the key when a path is missing', () => {
    expect(translate('en', 'missing.key')).toBe('missing.key')
  })
})
