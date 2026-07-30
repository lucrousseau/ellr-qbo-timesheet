import { describe, expect, it } from 'vitest'
import {
  DEFAULT_USER_LEVEL,
  normalizeUserLevel,
  USER_LEVELS,
} from './userLevel'

describe('userLevel', () => {
  it('lists supported permission codes in rank order', () => {
    expect(USER_LEVELS).toEqual(['employee', 'lead', 'manager'])
  })

  it('defaults unknown values to employee', () => {
    expect(normalizeUserLevel(null)).toBe(DEFAULT_USER_LEVEL)
    expect(normalizeUserLevel('unknown')).toBe('employee')
  })

  it('preserves supported permission codes', () => {
    expect(normalizeUserLevel('lead')).toBe('lead')
    expect(normalizeUserLevel('manager')).toBe('manager')
  })
})
