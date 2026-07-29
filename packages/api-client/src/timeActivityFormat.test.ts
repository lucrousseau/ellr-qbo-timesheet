import { describe, expect, it } from 'vitest'
import {
  formatTimeActivityDuration,
  fromDateTimeLocalValue,
  parseTimeActivityRow,
  toDateTimeLocalValue,
} from './timeActivityFormat'

describe('parseTimeActivityRow', () => {
  it('maps quickbooks fields into a display row', () => {
    const row = parseTimeActivityRow({
      Id: '12',
      StartTime: '2026-07-29T09:00:00',
      EndTime: '2026-07-29T11:34:00',
      CustomerRef: { value: '1', name: 'Acme Corp' },
      ItemRef: { value: '2', name: 'Programming' },
      Description: 'Support work',
      BillableStatus: 'NotBillable',
    })

    expect(row).toMatchObject({
      id: '12',
      customerName: 'Acme Corp',
      serviceName: 'Programming',
      description: 'Support work',
      isBillable: false,
      billableLocked: false,
      durationSeconds: 2 * 3600 + 34 * 60,
    })
  })

  it('marks billed activities as locked', () => {
    const row = parseTimeActivityRow({
      Id: '3',
      BillableStatus: 'HasBeenBilled',
    })

    expect(row.billableLocked).toBe(true)
  })

  it('combines enriched client and project reference names', () => {
    const row = parseTimeActivityRow({
      Id: '4',
      CustomerRef: { value: '58', name: 'Test ABC' },
      ProjectRef: { value: '22', name: 'Website' },
      ItemRef: { value: '6', name: 'Gardening' },
    })

    expect(row.customerName).toBe('Test ABC / Website')
    expect(row.serviceName).toBe('Gardening')
  })

  it('uses a single client label when project name matches the client', () => {
    const row = parseTimeActivityRow({
      Id: '5',
      CustomerRef: { value: '58', name: 'Test ABC' },
      ProjectRef: { value: '69630', name: 'Test ABC' },
    })

    expect(row.customerName).toBe('Test ABC')
  })
})

describe('formatTimeActivityDuration', () => {
  it('formats seconds as H:MM', () => {
    expect(formatTimeActivityDuration(2 * 3600 + 34 * 60)).toBe('2:34')
    expect(formatTimeActivityDuration(45 * 60)).toBe('0:45')
  })
})

describe('datetime-local helpers', () => {
  it('round-trips through datetime-local values', () => {
    const iso = '2026-07-29T14:30:00.000Z'
    const local = toDateTimeLocalValue(iso)

    expect(local).not.toBe('')
    expect(fromDateTimeLocalValue(local)).toBeTruthy()
  })
})
