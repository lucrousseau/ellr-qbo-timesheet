import { describe, expect, it, vi } from 'vitest'
import { computeElapsedSeconds, formatElapsedSeconds } from './timerFormat'

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
