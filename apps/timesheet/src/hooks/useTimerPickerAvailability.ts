/**
 * @file Prefetches assigned customers and derives timer picker visibility.
 */

import { useEffect, useRef, useState } from 'react'
import {
  fetchQboCustomers,
  fetchQboProjects,
  qboRefsMatch,
  type QboPickerOption,
  type UserLocale,
} from '@ellr/api-client'
import { getApiErrorMessage } from '@ellr/ui'

type PickerLoadStatus = 'unknown' | 'empty' | 'available' | 'error'

type UseTimerPickerAvailabilityOptions = {
  enabled: boolean
  loading: boolean
  customer: QboPickerOption | null
  project: QboPickerOption | null
  service: QboPickerOption | null
  onError: (message: string) => void
  loadCustomersFailedMessage: string
  loadProjectsFailedMessage: string
  locale: UserLocale
}

/**
 * Prefetches customers on mount; services and projects load lazily when comboboxes open.
 * @param options Enable flags, selected values, and error handling.
 * @returns Customer list, load status, and picker visibility flags.
 */
export function useTimerPickerAvailability({
  enabled,
  loading,
  customer,
  project,
  service,
  onError,
  loadCustomersFailedMessage,
  loadProjectsFailedMessage,
  locale,
}: UseTimerPickerAvailabilityOptions) {
  const [customers, setCustomers] = useState<QboPickerOption[]>([])
  const [projects, setProjects] = useState<QboPickerOption[]>([])
  const [customersStatus, setCustomersStatus] = useState<PickerLoadStatus>('unknown')
  const [projectsStatus, setProjectsStatus] = useState<PickerLoadStatus>('unknown')
  const onErrorRef = useRef(onError)
  onErrorRef.current = onError
  const customerRef = customer?.id ?? null
  const timerReady = enabled && !loading

  useEffect(() => {
    if (!enabled || loading) {
      setCustomers([])
      setCustomersStatus('unknown')

      return
    }

    const controller = new AbortController()

    const loadCustomers = async () => {
      setCustomers([])
      setCustomersStatus('unknown')

      try {
        const nextCustomers = await fetchQboCustomers({ signal: controller.signal })

        if (controller.signal.aborted) {
          return
        }

        setCustomers(nextCustomers)
        setCustomersStatus(nextCustomers.length > 0 ? 'available' : 'empty')
      } catch (caught) {
        if (controller.signal.aborted) {
          return
        }

        setCustomers([])
        setCustomersStatus('error')
        onErrorRef.current(getApiErrorMessage(caught, loadCustomersFailedMessage, locale))
      }
    }

    void loadCustomers()

    return () => {
      controller.abort()
    }
  }, [enabled, loadCustomersFailedMessage, loading, locale])

  useEffect(() => {
    if (!enabled || loading || customerRef === null) {
      setProjects([])
      setProjectsStatus('unknown')

      return
    }

    const selectedCustomerRef = customerRef
    const controller = new AbortController()

    const loadProjects = async () => {
      setProjects([])
      setProjectsStatus('unknown')

      try {
        const nextProjects = await fetchQboProjects(selectedCustomerRef, { signal: controller.signal })

        if (controller.signal.aborted) {
          return
        }

        setProjects(nextProjects)
        setProjectsStatus(nextProjects.length > 0 ? 'available' : 'empty')
      } catch (caught) {
        if (controller.signal.aborted) {
          return
        }

        setProjects([])
        setProjectsStatus('error')
        onErrorRef.current(getApiErrorMessage(caught, loadProjectsFailedMessage, locale))
      }
    }

    void loadProjects()

    return () => {
      controller.abort()
    }
  }, [customerRef, enabled, loadProjectsFailedMessage, loading, locale])

  const showCustomerPicker = customer !== null || customersStatus === 'available'
  const showServicePicker = service !== null || timerReady
  const showProjectPicker =
    customer !== null && (project !== null || projectsStatus === 'available')

  const isCustomerAllowed = (() => {
    if (customer === null) {
      return true
    }

    if (customersStatus === 'unknown' || customersStatus === 'error') {
      return true
    }

    if (customersStatus === 'empty') {
      return false
    }

    return customers.some((item) => qboRefsMatch(item.id, customer.id))
  })()

  return {
    customers,
    services: [] as QboPickerOption[],
    projects,
    customersStatus,
    servicesStatus: 'unknown' as PickerLoadStatus,
    projectsStatus,
    showCustomerPicker,
    showServicePicker,
    showProjectPicker,
    isCustomerAllowed,
    isProjectAllowed: true,
    isServiceAllowed: true,
  }
}
