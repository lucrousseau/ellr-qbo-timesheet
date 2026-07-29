import { describe, expect, it } from 'vitest'
import { normalizeQboRef, qboRefsMatch } from './qboRef'

describe('qboRef', () => {
  it('normalizes quickbooks identifiers to numeric strings', () => {
    expect(normalizeQboRef('11')).toBe('11')
    expect(normalizeQboRef('Customer-11')).toBe('11')
  })

  it('matches quickbooks identifiers with different formatting', () => {
    expect(qboRefsMatch('11', 'Customer-11')).toBe(true)
    expect(qboRefsMatch('11', '12')).toBe(false)
  })
})
