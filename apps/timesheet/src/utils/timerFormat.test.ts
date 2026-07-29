import { describe, expect, it, vi } from 'vitest'
import {
  computeElapsedSeconds,
  formatElapsedInput,
  formatElapsedSeconds,
  parseElapsedInput,
} from './timerFormat'

describe('formatElapsedSeconds', () => {
  it('formats whole seconds as zero-padded HH:MM:SS', () => {
    expect(formatElapsedSeconds(3661)).toEqual({
      hours: '01',
      minutes: '01',
      seconds: '01',
    })
  })

  it('clamps negative values to zero', () => {
    expect(formatElapsedSeconds(-12)).toEqual({
      hours: '00',
      minutes: '00',
      seconds: '00',
    })
  })
})

describe('formatElapsedInput', () => {
  it('formats elapsed seconds as HH:MM for editable inputs', () => {
    expect(formatElapsedInput(3661)).toBe('01:01')
  })
})

describe('parseElapsedInput', () => {
  it('parses HH:MM values', () => {
    expect(parseElapsedInput('01:30')).toBe(5400)
  })

  it('rejects HH:MM:SS values', () => {
    expect(parseElapsedInput('01:02:03')).toBeNull()
  })

  it('returns null for invalid values', () => {
    expect(parseElapsedInput('abc')).toBeNull()
    expect(parseElapsedInput('01:99')).toBeNull()
  })

  it('treats blank input as zero seconds', () => {
    expect(parseElapsedInput('   ')).toBe(0)
  })
})

describe('computeElapsedSeconds', () => {
  it('returns accumulated seconds when the timer is paused', () => {
    expect(computeElapsedSeconds(120, null)).toBe(120)
  })

  it('adds elapsed time since running_since', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-28T12:00:30.000Z'))

    expect(computeElapsedSeconds(120, '2026-07-28T12:00:00.000Z')).toBe(150)

    vi.useRealTimers()
  })

  it('ignores invalid running_since timestamps', () => {
    expect(computeElapsedSeconds(90, 'invalid')).toBe(90)
  })
})
