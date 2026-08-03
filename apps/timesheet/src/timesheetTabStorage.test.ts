import { describe, expect, it } from 'vitest'
import { isTimesheetTab, timesheetActiveTabStorageKey } from './timesheetTabStorage'

describe('timesheetTabStorage', () => {
  it('builds a per-user storage key', () => {
    expect(timesheetActiveTabStorageKey(42)).toBe('ellr.timesheet.activeTab:42')
  })

  it('accepts known tab ids', () => {
    expect(isTimesheetTab('timer')).toBe(true)
    expect(isTimesheetTab('expenses')).toBe(true)
    expect(isTimesheetTab('preferences')).toBe(true)
    expect(isTimesheetTab('unknown')).toBe(false)
  })
})
