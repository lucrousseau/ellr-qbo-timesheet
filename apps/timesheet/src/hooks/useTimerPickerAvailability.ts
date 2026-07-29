/**
 * @file Prefetches QBO picker lists and derives conditional picker visibility.
 */

import { useEffect, useRef, useState } from 'react'
import {
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
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
  loadServicesFailedMessage: string
  locale: UserLocale
}

/**
 * Prefetches customers and services on mount, and projects when a client is selected.
 * @param options Enable flags, selected values, and error handling.
 * @returns Prefetched lists, load status, and picker visibility flags.
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
  loadServicesFailedMessage,
  locale,
}: UseTimerPickerAvailabilityOptions) {
  const [customers, setCustomers] = useState<QboPickerOption[]>([])
  const [services, setServices] = useState<QboPickerOption[]>([])
  const [projects, setProjects] = useState<QboPickerOption[]>([])
  const [customersStatus, setCustomersStatus] = useState<PickerLoadStatus>('unknown')
  const [servicesStatus, setServicesStatus] = useState<PickerLoadStatus>('unknown')
  const [projectsStatus, setProjectsStatus] = useState<PickerLoadStatus>('unknown')
  const onErrorRef = useRef(onError)
  onErrorRef.current = onError
  const customerRef = customer?.id ?? null

  useEffect(() => {
    if (!enabled || loading) {
      setCustomers([])
      setServices([])
      setCustomersStatus('unknown')
      setServicesStatus('unknown')

      return
    }

    const controller = new AbortController()

    const loadSharedPickers = async () => {
      setCustomers([])
      setServices([])
      setCustomersStatus('unknown')
      setServicesStatus('unknown')

      try {
        const [customersResult, servicesResult] = await Promise.allSettled([
          fetchQboCustomers({ signal: controller.signal }),
          fetchQboServices({ signal: controller.signal }),
        ])

        if (controller.signal.aborted) {
          return
        }

        if (customersResult.status === 'fulfilled') {
          setCustomers(customersResult.value)
          setCustomersStatus(customersResult.value.length > 0 ? 'available' : 'empty')
        } else {
          setCustomers([])
          setCustomersStatus('error')
          onErrorRef.current(
            getApiErrorMessage(customersResult.reason, loadCustomersFailedMessage, locale),
          )
        }

        if (servicesResult.status === 'fulfilled') {
          setServices(servicesResult.value)
          setServicesStatus(servicesResult.value.length > 0 ? 'available' : 'empty')
        } else {
          setServices([])
          setServicesStatus('error')
          onErrorRef.current(getApiErrorMessage(servicesResult.reason, loadServicesFailedMessage, locale))
        }
      } catch {
        if (controller.signal.aborted) {
          return
        }

        setCustomers([])
        setServices([])
        setCustomersStatus('error')
        setServicesStatus('error')
      }
    }

    void loadSharedPickers()

    return () => {
      controller.abort()
    }
  }, [enabled, loadCustomersFailedMessage, loadServicesFailedMessage, loading, locale])

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
  const showServicePicker = service !== null || servicesStatus === 'available'
  const showProjectPicker =
    customer !== null && (project !== null || projectsStatus === 'available')

  const isOptionAllowed = (
    selected: QboPickerOption | null,
    status: PickerLoadStatus,
    items: QboPickerOption[],
  ): boolean => {
    if (selected === null) {
      return true
    }

    if (status === 'unknown' || status === 'error') {
      return true
    }

    if (status === 'empty') {
      return false
    }

    return items.some((item) => qboRefsMatch(item.id, selected.id))
  }

  const isCustomerAllowed = isOptionAllowed(customer, customersStatus, customers)
  const isProjectAllowed = isOptionAllowed(project, projectsStatus, projects)
  const isServiceAllowed = isOptionAllowed(service, servicesStatus, services)

  return {
    customers,
    services,
    projects,
    customersStatus,
    servicesStatus,
    projectsStatus,
    showCustomerPicker,
    showServicePicker,
    showProjectPicker,
    isCustomerAllowed,
    isProjectAllowed,
    isServiceAllowed,
  }
}
