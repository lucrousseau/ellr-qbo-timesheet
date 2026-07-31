/**
 * @file Tests for timer picker visibility with customer prefetch.
 */

import { fetchQboCustomers, fetchQboProjects } from '@ellr/api-client'
import { renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTimerPickerAvailability } from './useTimerPickerAvailability'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
  }
})

const baseOptions = {
  enabled: true,
  loading: false,
  customer: null,
  project: null,
  service: null,
  onError: vi.fn(),
  loadCustomersFailedMessage: 'Failed to load customers',
  loadProjectsFailedMessage: 'Failed to load projects',
  locale: 'en' as const,
}

describe('useTimerPickerAvailability', () => {
  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])
    vi.mocked(fetchQboProjects).mockResolvedValue([{ id: '22', display_name: 'Website' }])
  })

  it('shows the customer picker when assigned customers are available', async () => {
    const { result } = renderHook(() => useTimerPickerAvailability(baseOptions))

    await waitFor(() => {
      expect(result.current.customersStatus).toBe('available')
    })

    expect(result.current.showCustomerPicker).toBe(true)
    expect(result.current.showServicePicker).toBe(true)
    expect(result.current.showProjectPicker).toBe(false)
  })

  it('shows the project picker when a customer is selected', async () => {
    const { result } = renderHook(() =>
      useTimerPickerAvailability({
        ...baseOptions,
        customer: { id: '11', display_name: 'Acme Corp' },
      }),
    )

    await waitFor(() => {
      expect(result.current.projectsStatus).toBe('available')
    })

    expect(result.current.showProjectPicker).toBe(true)
  })

  it('hides pickers while the timer session is still loading', () => {
    const { result } = renderHook(() =>
      useTimerPickerAvailability({
        ...baseOptions,
        loading: true,
      }),
    )

    expect(result.current.showCustomerPicker).toBe(false)
    expect(result.current.showServicePicker).toBe(false)
  })

  it('does not prefetch customers when the hook is disabled', async () => {
    renderHook(() =>
      useTimerPickerAvailability({
        ...baseOptions,
        enabled: false,
      }),
    )

    await waitFor(() => {
      expect(fetchQboCustomers).not.toHaveBeenCalled()
    })
  })
})
