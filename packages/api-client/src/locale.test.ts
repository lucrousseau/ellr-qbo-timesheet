/**
 * @file Tests for locale normalization helpers.
 */

import { describe, expect, it } from 'vitest'
import { normalizeUserLocale, SUPPORTED_LOCALES } from './locale'

describe('normalizeUserLocale', () => {
  it('returns french only for the fr code', () => {
    expect(normalizeUserLocale('fr')).toBe('fr')
    expect(normalizeUserLocale('en')).toBe('en')
    expect(normalizeUserLocale(null)).toBe('en')
    expect(normalizeUserLocale(undefined)).toBe('en')
    expect(normalizeUserLocale('de')).toBe('en')
  })

  it('exposes supported locales in display order', () => {
    expect(SUPPORTED_LOCALES).toEqual(['en', 'fr'])
  })
})
