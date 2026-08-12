/**
 * @file Tests for the grouped QuickBooks sync detail dialog.
 */

import { fetchTimeEntrySyncGroup } from '@ellr/api-client'
import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeEntrySyncGroupDialog } from './TimeEntrySyncGroupDialog'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchTimeEntrySyncGroup: vi.fn(),
  }
})

describe('TimeEntrySyncGroupDialog', () => {
  beforeEach(() => {
    vi.mocked(fetchTimeEntrySyncGroup).mockReset()
  })

  it('loads and shows grouped member entries', async () => {
    vi.mocked(fetchTimeEntrySyncGroup).mockResolvedValue({
      id: 1,
      public_id: '11111111-1111-4111-8111-111111111111',
      user_id: 3,
      employee_name: 'Jane Doe',
      txn_date: '2026-08-12',
      is_billable: true,
      member_count: 2,
      total_duration_seconds: 7200,
      qbo_id: '900',
      detail_url: 'http://admin.test/?sync_group=11111111-1111-4111-8111-111111111111',
      entries: [
        {
          id: 10,
          list_id: 'local:10',
          user_id: 3,
          start_time: '2026-08-12T13:00:00Z',
          end_time: '2026-08-12T14:00:00Z',
          duration_seconds: 3600,
          description: 'Morning block',
          is_billable: true,
          status: 'approved',
        },
        {
          id: 11,
          list_id: 'local:11',
          user_id: 3,
          start_time: '2026-08-12T15:00:00Z',
          end_time: '2026-08-12T16:00:00Z',
          duration_seconds: 3600,
          description: 'Afternoon block',
          is_billable: true,
          status: 'approved',
        },
      ],
    })

    render(
      <LocaleProvider>
        <TimeEntrySyncGroupDialog
          publicId="11111111-1111-4111-8111-111111111111"
          onClose={() => undefined}
        />
      </LocaleProvider>,
    )

    await waitFor(() => {
      expect(screen.getByText('Morning block')).toBeInTheDocument()
    })

    expect(screen.getByText('Afternoon block')).toBeInTheDocument()
    expect(screen.getByText('Jane Doe')).toBeInTheDocument()
    expect(fetchTimeEntrySyncGroup).toHaveBeenCalledWith('11111111-1111-4111-8111-111111111111')
  })
})
