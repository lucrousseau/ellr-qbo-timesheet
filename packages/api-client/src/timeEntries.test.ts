/**
 * @file Tests for time entry API helpers.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiFetch } from './api'
import {
  approveTimeEntry,
  createTimeEntry,
  deleteTimeEntry,
  listPendingTimeEntryApprovals,
  listTimeEntries,
  rejectTimeEntry,
  resolveTimeEntryId,
  returnTimeEntryToDraft,
  submitTimeEntries,
  submitTimeEntry,
  updateAdminUserSupervisor,
  updatePendingTimeEntryApproval,
  updateTimeEntry,
  type TimeEntry,
} from './timeEntries'

vi.mock('./api', () => ({
  apiFetch: vi.fn(),
}))

const sampleEntry: TimeEntry = {
  id: 12,
  list_id: 'local:12',
  user_id: 2,
  start_time: '2026-07-30T17:00:00Z',
  end_time: '2026-07-30T18:00:00Z',
  duration_seconds: 3600,
  is_billable: false,
  status: 'draft',
}

describe('time entry api', () => {
  beforeEach(() => {
    vi.mocked(apiFetch).mockReset()
  })

  it('lists time entries with optional pagination', async () => {
    const response = {
      data: [sampleEntry],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    }
    vi.mocked(apiFetch).mockResolvedValue(response)

    await expect(listTimeEntries({ start_position: 1, max_results: 10 })).resolves.toEqual(response)
    expect(apiFetch).toHaveBeenCalledWith('/time-entries?start_position=1&max_results=10')
  })

  it('creates a draft time entry', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: sampleEntry })

    await expect(
      createTimeEntry({
        start_time: sampleEntry.start_time,
        end_time: sampleEntry.end_time,
      }),
    ).resolves.toEqual(sampleEntry)

    expect(apiFetch).toHaveBeenCalledWith('/time-entries', {
      method: 'POST',
      body: JSON.stringify({
        start_time: sampleEntry.start_time,
        end_time: sampleEntry.end_time,
      }),
    })
  })

  it('updates a draft time entry', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { ...sampleEntry, description: 'Updated' } })

    await expect(updateTimeEntry(12, { description: 'Updated' })).resolves.toMatchObject({
      description: 'Updated',
    })

    expect(apiFetch).toHaveBeenCalledWith('/time-entries/12', {
      method: 'PATCH',
      body: JSON.stringify({ description: 'Updated' }),
    })
  })

  it('submits a draft time entry', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { ...sampleEntry, status: 'pending' } })

    await expect(submitTimeEntry(12)).resolves.toMatchObject({ status: 'pending' })
    expect(apiFetch).toHaveBeenCalledWith('/time-entries/12/submit', { method: 'POST' })
  })

  it('submits all draft time entries', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: [{ ...sampleEntry, status: 'pending' }] })

    await expect(submitTimeEntries()).resolves.toHaveLength(1)
    expect(apiFetch).toHaveBeenCalledWith('/time-entries/submit', {
      method: 'POST',
      body: JSON.stringify({}),
    })
  })

  it('updates a pending approval entry through the admin route', async () => {
    vi.mocked(apiFetch).mockResolvedValue({
      data: { ...sampleEntry, status: 'pending', description: 'Fixed' },
    })

    await expect(
      updatePendingTimeEntryApproval(12, { description: 'Fixed' }, { admin: true }),
    ).resolves.toMatchObject({ description: 'Fixed' })

    expect(apiFetch).toHaveBeenCalledWith('/admin/time-entry-approvals/12', {
      method: 'PATCH',
      body: JSON.stringify({ description: 'Fixed' }),
    })
  })

  it('deletes a local time entry', async () => {
    vi.mocked(apiFetch).mockResolvedValue(undefined)

    await expect(deleteTimeEntry(12)).resolves.toBeUndefined()
    expect(apiFetch).toHaveBeenCalledWith('/time-entries/12', { method: 'DELETE' })
  })

  it('lists supervisor pending approvals', async () => {
    const response = {
      data: [sampleEntry],
      meta: { count: 1, max_results: 10, start_position: 1, truncated: false },
    }
    vi.mocked(apiFetch).mockResolvedValue(response)

    await expect(listPendingTimeEntryApprovals()).resolves.toEqual(response)
    expect(apiFetch).toHaveBeenCalledWith('/time-entry-approvals')
  })

  it('lists admin pending approvals with pagination', async () => {
    const response = {
      data: [sampleEntry],
      meta: { count: 1, max_results: 5, start_position: 6, truncated: true },
    }
    vi.mocked(apiFetch).mockResolvedValue(response)

    await expect(
      listPendingTimeEntryApprovals({ start_position: 6, max_results: 5 }, { admin: true }),
    ).resolves.toEqual(response)

    expect(apiFetch).toHaveBeenCalledWith(
      '/admin/time-entry-approvals?start_position=6&max_results=5',
    )
  })

  it('approves a pending entry through the supervisor route', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { ...sampleEntry, status: 'approved' } })

    await expect(approveTimeEntry(12)).resolves.toMatchObject({ status: 'approved' })
    expect(apiFetch).toHaveBeenCalledWith('/time-entry-approvals/12/approve', {
      method: 'POST',
    })
  })

  it('rejects a pending entry through the admin route', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { ...sampleEntry, status: 'rejected' } })

    await expect(rejectTimeEntry(12, 'Incomplete notes', { admin: true })).resolves.toMatchObject({
      status: 'rejected',
    })

    expect(apiFetch).toHaveBeenCalledWith('/admin/time-entry-approvals/12/reject', {
      method: 'POST',
      body: JSON.stringify({ reason: 'Incomplete notes' }),
    })
  })

  it('returns a pending entry to draft through the admin route', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { ...sampleEntry, status: 'draft' } })

    await expect(returnTimeEntryToDraft(12, { admin: true })).resolves.toMatchObject({
      status: 'draft',
    })

    expect(apiFetch).toHaveBeenCalledWith('/admin/time-entry-approvals/12/return-to-draft', {
      method: 'POST',
    })
  })

  it('assigns a supervisor for a provisioned user', async () => {
    vi.mocked(apiFetch).mockResolvedValue({ data: { supervisor_id: 3 } })

    await expect(updateAdminUserSupervisor(2, 3)).resolves.toEqual({ supervisor_id: 3 })
    expect(apiFetch).toHaveBeenCalledWith('/admin/users/2/supervisor', {
      method: 'PATCH',
      body: JSON.stringify({ supervisor_id: 3 }),
    })
  })
})

describe('resolveTimeEntryId', () => {
  it('parses prefixed local list ids', () => {
    expect(resolveTimeEntryId('local:12')).toBe(12)
  })

  it('parses bare numeric ids', () => {
    expect(resolveTimeEntryId('12')).toBe(12)
  })

  it('rejects invalid ids', () => {
    expect(() => resolveTimeEntryId('local:abc')).toThrow(/Invalid time entry id/)
  })
})
