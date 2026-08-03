/**
 * @file Tests for timer persistence when editing elapsed time and toggling play/pause.
 */

import { LocaleProvider } from '@ellr/ui'
import { act, renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import {
  DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  fetchAppConfig,
  fetchTimeTracker,
  logTimeTracker,
  updateTimeTracker,
} from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTimeTracker } from './useTimeTracker'

const editedDurationSeconds = 12 * 3600 + 34 * 60

function buildSession(overrides: Record<string, unknown> = {}) {
  return {
    customer_ref: null,
    customer_name: null,
    project_ref: null,
    project_name: null,
    service_ref: null,
    service_name: null,
    description: null,
    ticket_key: null,
    ticket_source: null,
    ticket_url: null,
    ticket_title: null,
    is_billable: false,
    accumulated_seconds: 0,
    running_since: null,
    elapsed_seconds: 0,
    is_running: false,
    ...overrides,
  }
}

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
    fetchQboServices: vi.fn(),
    fetchAppConfig: vi.fn(),
    fetchTimeTracker: vi.fn(),
    updateTimeTracker: vi.fn(),
    logTimeTracker: vi.fn(),
  }
})

function wrapper({ children }: { children: ReactNode }) {
  return <LocaleProvider>{children}</LocaleProvider>
}

describe('useTimeTracker elapsed persistence', () => {
  beforeEach(() => {
    vi.mocked(fetchAppConfig).mockResolvedValue({
      require_email_verification: false,
      time_tracker_max_accumulated_seconds: DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
      ticket_integrations: { jira_enabled: false, linear_enabled: false },
    })
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([])
    vi.mocked(fetchQboServices).mockResolvedValue([])
    vi.mocked(fetchTimeTracker).mockResolvedValue(buildSession())
    vi.mocked(logTimeTracker).mockResolvedValue({
      id: 1,
      list_id: 'local:1',
      user_id: 1,
      start_time: '2026-07-27T09:00:00Z',
      end_time: '2026-07-27T17:00:00Z',
      duration_seconds: 28_800,
      is_billable: false,
      status: 'pending',
    })
    vi.mocked(updateTimeTracker).mockImplementation(async (payload) => {
      const accumulated = payload.accumulated_seconds ?? 0

      return buildSession({
        ...payload,
        accumulated_seconds: accumulated,
        elapsed_seconds: accumulated,
        running_since: payload.is_running ? new Date().toISOString() : null,
        is_running: payload.is_running,
      })
    })
  })

  it('syncs manual elapsed edits before starting the timer', async () => {
    const { result } = renderHook(() => useTimeTracker(true), { wrapper })

    await waitFor(() => {
      expect(result.current.loading).toBe(false)
    })

    act(() => {
      result.current.onElapsedChange(editedDurationSeconds)
    })

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(
        expect.objectContaining({
          accumulated_seconds: editedDurationSeconds,
          is_running: false,
        }),
      )
    })

    await act(async () => {
      await result.current.onToggleTimer()
    })

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenLastCalledWith(
        expect.objectContaining({
          accumulated_seconds: editedDurationSeconds,
          is_running: true,
        }),
      )
    })
  })

  it('pauses with the displayed elapsed time before logging', async () => {
    const runningSince = '2026-07-30T17:00:00.000Z'
    vi.mocked(fetchTimeTracker).mockResolvedValue(
      buildSession({
        accumulated_seconds: editedDurationSeconds,
        elapsed_seconds: editedDurationSeconds + 5,
        running_since: runningSince,
        is_running: true,
      }),
    )

    vi.mocked(updateTimeTracker).mockImplementation(async (payload) => {
      const accumulated = payload.accumulated_seconds ?? editedDurationSeconds + 5

      return buildSession({
        ...payload,
        accumulated_seconds: accumulated,
        elapsed_seconds: accumulated,
        running_since: payload.is_running ? runningSince : null,
        is_running: payload.is_running,
      })
    })

    const { result } = renderHook(() => useTimeTracker(true), { wrapper })

    await waitFor(() => {
      expect(result.current.loading).toBe(false)
      expect(result.current.elapsedSeconds).toBeGreaterThanOrEqual(editedDurationSeconds)
    })

    await act(async () => {
      await result.current.onLogTime()
    })

    expect(updateTimeTracker).toHaveBeenCalledWith(
      expect.objectContaining({
        accumulated_seconds: expect.any(Number),
        is_running: false,
      }),
    )
    expect(logTimeTracker).toHaveBeenCalled()
  })

  it('syncs ticket key metadata on blur', async () => {
    const { result } = renderHook(() => useTimeTracker(true), { wrapper })

    await waitFor(() => {
      expect(result.current.loading).toBe(false)
    })

    act(() => {
      result.current.onTicketKeyChange('ELLR-42')
    })

    expect(result.current.state.ticketKey).toBe('ELLR-42')

    await act(async () => {
      result.current.onTicketKeyBlur()
    })

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(
        expect.objectContaining({
          ticket_key: 'ELLR-42',
          ticket_source: 'manual',
        }),
      )
    })
  })

  it('clears ticket metadata when the key is emptied', async () => {
    vi.mocked(fetchTimeTracker).mockResolvedValue(
      buildSession({
        ticket_key: 'ELLR-42',
        ticket_source: 'manual',
        ticket_url: 'https://example.com/ELLR-42',
        ticket_title: 'Auth fix',
      }),
    )

    const { result } = renderHook(() => useTimeTracker(true), { wrapper })

    await waitFor(() => {
      expect(result.current.state.ticketKey).toBe('ELLR-42')
    })

    act(() => {
      result.current.onTicketKeyChange('')
    })

    await act(async () => {
      result.current.onTicketKeyBlur()
    })

    await waitFor(() => {
      expect(updateTimeTracker).toHaveBeenCalledWith(
        expect.objectContaining({
          ticket_key: null,
          ticket_source: null,
          ticket_url: null,
          ticket_title: null,
        }),
      )
    })
  })
})
