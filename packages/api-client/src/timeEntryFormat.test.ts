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
      sync_group_public_id: '11111111-1111-4111-8111-111111111111',
      sync_group_member_count: 3,
      list_id: 'local:12',
    })

    expect(row).toMatchObject({
      id: 'local:12',
      timeEntryId: 12,
      customerName: 'Acme / Website',
      serviceName: 'Consulting',
      approvalStatus: 'pending',
      employeeName: 'Jane Doe',
      syncGroupPublicId: '11111111-1111-4111-8111-111111111111',
      syncGroupMemberCount: 3,
    })
  })

  it('falls back to refs when display names are missing', () => {
    const row = parseTimeEntryRow({
      id: 13,
      user_id: 3,
      customer_ref: '11',
      customer_name: null,
      project_ref: '22',
      project_name: null,
      item_ref: '9',
      item_name: null,
      start_time: '2026-07-27T09:00:00Z',
      end_time: '2026-07-27T17:00:00Z',
      duration_seconds: 28_800,
      description: 'Draft',
      is_billable: true,
      status: 'pending',
      qbo_id: null,
      list_id: 'local:13',
    })

    expect(row).toMatchObject({
      customerName: '11 / 22',
      serviceName: '9',
    })
  })
})
