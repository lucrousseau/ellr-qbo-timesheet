/**
 * @file Tests for locale provider error handling.
 */

import { renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useLocale } from './LocaleProvider'

describe('useLocale', () => {
  it('throws when used outside LocaleProvider', () => {
    expect(() => renderHook(() => useLocale())).toThrow('useLocale must be used within a LocaleProvider')
  })
})
