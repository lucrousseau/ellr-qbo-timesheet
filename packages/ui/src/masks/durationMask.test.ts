/**
 * @file Tests for duration mask presets.
 */

import IMask from 'imask'
import { describe, expect, it } from 'vitest'
import { createDurationHoursMinutesMask, durationHoursMinutesMask, capDurationMaskValue } from './durationMask'

describe('durationHoursMinutesMask', () => {
  it('formats 1234 as 12:34 on a native input', () => {
    const input = document.createElement('input')
    document.body.append(input)

    const mask = IMask(input, durationHoursMinutesMask)
    mask.typedValue = '1234'

    expect(mask.value).toBe('12:34')

    mask.destroy()
    input.remove()
  })

  it('caps minutes at 59', () => {
    const input = document.createElement('input')
    document.body.append(input)

    const mask = IMask(input, durationHoursMinutesMask)
    mask.typedValue = '0199'

    expect(mask.value).toBe('01:59')

    mask.destroy()
    input.remove()
  })

  it('caps duration to the API maximum of 24 hours', () => {
    expect(capDurationMaskValue('25:00', 86_400)).toBe('24:00')
    expect(capDurationMaskValue('24:59', 86_400)).toBe('24:00')

    const input = document.createElement('input')
    document.body.append(input)

    const mask = IMask(input, createDurationHoursMinutesMask(86_400))
    mask.typedValue = '2500'

    expect(mask.value).toBe('24:00')

    mask.destroy()
    input.remove()
  })
})
