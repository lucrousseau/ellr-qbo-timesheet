/**
 * @file Tests for local time entry row normalization.
 */

import { describe, expect, it } from 'vitest'
import { parseTimeEntryRow } from './timeEntryFormat'

describe('parseTimeEntryRow', () => {
  it('maps approval metadata and combined client labels', () => {
    const row = parseTimeEntryRow({
      id: 12,
      user_id: 3,
      employee_name: 'Jane Doe',
      customer_ref: '1',
      customer_name: 'Acme',
      project_ref: '2',
      project_name: 'Website',
      item_ref: '9',
      item_name: 'Consulting',
      start_time: '2026-07-27T09:00:00Z',
      end_time: '2026-07-27T17:00:00Z',
      duration_seconds: 28_800,
      description: 'Draft',
      is_billable: true,
      status: 'pending',
      qbo_id: null,
      list_id: 'local:12',
    })

    expect(row).toMatchObject({
      id: 'local:12',
      timeEntryId: 12,
      customerName: 'Acme / Website',
      serviceName: 'Consulting',
      approvalStatus: 'pending',
      employeeName: 'Jane Doe',
    })
  })
})
