/**
 * @file Tests for timer picker availability prefetch and visibility rules.
 */

import { renderHook, waitFor } from '@testing-library/react'
import { fetchQboCustomers, fetchQboProjects, fetchQboServices } from '@ellr/api-client'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTimerPickerAvailability } from './useTimerPickerAvailability'

vi.mock('@ellr/api-client', async () => {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchQboCustomers: vi.fn(),
    fetchQboProjects: vi.fn(),
    fetchQboServices: vi.fn(),
  }
})

describe('useTimerPickerAvailability', () => {
  const onError = vi.fn()

  beforeEach(() => {
    vi.mocked(fetchQboCustomers).mockReset()
    vi.mocked(fetchQboProjects).mockReset()
    vi.mocked(fetchQboServices).mockReset()
    onError.mockReset()
    vi.mocked(fetchQboCustomers).mockResolvedValue([])
    vi.mocked(fetchQboServices).mockResolvedValue([])
    vi.mocked(fetchQboProjects).mockResolvedValue([])
  })

  it('shows customer and service pickers when quickbooks returns options', async () => {
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])
    vi.mocked(fetchQboServices).mockResolvedValue([{ id: '33', display_name: 'Consulting' }])

    const { result } = renderHook(() =>
      useTimerPickerAvailability({
        enabled: true,
        loading: false,
        customer: null,
        project: null,
        service: null,
        onError,
        loadCustomersFailedMessage: 'Customers failed',
        loadProjectsFailedMessage: 'Projects failed',
        loadServicesFailedMessage: 'Services failed',
        locale: 'en',
      }),
    )

    await waitFor(() => {
      expect(result.current.showCustomerPicker).toBe(true)
      expect(result.current.showServicePicker).toBe(true)
    })
  })

  it('reports picker load failures without treating them as empty lists', async () => {
    vi.mocked(fetchQboCustomers).mockRejectedValue(new Error('offline'))

    const { result } = renderHook(() =>
      useTimerPickerAvailability({
        enabled: true,
        loading: false,
        customer: null,
        project: null,
        service: null,
        onError,
        loadCustomersFailedMessage: 'Customers failed',
        loadProjectsFailedMessage: 'Projects failed',
        loadServicesFailedMessage: 'Services failed',
        locale: 'en',
      }),
    )

    await waitFor(() => {
      expect(onError).toHaveBeenCalled()
      expect(result.current.showCustomerPicker).toBe(false)
      expect(result.current.customersStatus).toBe('error')
    })
  })

  it('clears stale selections once allowed lists are loaded', async () => {
    vi.mocked(fetchQboCustomers).mockResolvedValue([{ id: '11', display_name: 'Acme Corp' }])

    const staleCustomer = { id: '99', display_name: 'Stale Corp' }

    const { result } = renderHook(() =>
      useTimerPickerAvailability({
        enabled: true,
        loading: false,
        customer: staleCustomer,
        project: null,
        service: null,
        onError,
        loadCustomersFailedMessage: 'Customers failed',
        loadProjectsFailedMessage: 'Projects failed',
        loadServicesFailedMessage: 'Services failed',
        locale: 'en',
      }),
    )

    await waitFor(() => {
      expect(result.current.customersStatus).toBe('available')
      expect(result.current.isCustomerAllowed).toBe(false)
    })
  })
})
