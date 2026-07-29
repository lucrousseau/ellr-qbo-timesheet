/**
 * @file Persistent timer state and QBO picker orchestration for the timesheet app.
 */

import { useCallback, useEffect, useRef, useState } from 'react'
import {
  discardTimeTracker,
  fetchQboCustomers,
  fetchQboProjects,
  fetchQboServices,
  fetchTimeTracker,
  logTimeTracker,
  updateTimeTracker,
  type QboPickerOption,
  type TimeTrackerSession,
} from '@ellr/api-client'
import { getApiErrorMessage, useFlashMessage, useGuardedAction, useLazyApiSelect, useLocale } from '@ellr/ui'
import { useTimerPickerAvailability } from './useTimerPickerAvailability'
import { computeElapsedSeconds } from '../utils/timerFormat'

export type { QboPickerOption } from '@ellr/api-client'

type TimerState = {
  customer: QboPickerOption | null
  project: QboPickerOption | null
  service: QboPickerOption | null
  description: string
  accumulatedSeconds: number
  runningSince: string | null
}

const emptyTimerState = (): TimerState => ({
  customer: null,
  project: null,
  service: null,
  description: '',
  accumulatedSeconds: 0,
  runningSince: null,
})

/**
 * Maps a server session into local picker and timer state.
 * @param session Active timer session from the API.
 * @returns Local timer state.
 */
function sessionToState(session: TimeTrackerSession): TimerState {
  return {
    customer: session.customer_ref
      ? {
          id: session.customer_ref,
          display_name: session.customer_name ?? session.customer_ref,
        }
      : null,
    project: session.project_ref
      ? {
          id: session.project_ref,
          display_name: session.project_name ?? session.project_ref,
        }
      : null,
    service: session.service_ref
      ? {
          id: session.service_ref,
          display_name: session.service_name ?? session.service_ref,
        }
      : null,
    description: session.description ?? '',
    accumulatedSeconds: session.accumulated_seconds,
    runningSince: session.is_running ? session.running_since : null,
  }
}

/**
 * Builds the API payload from local timer state.
 * @param state Local timer state.
 * @returns Payload for PUT /time-tracker.
 */
function stateToPayload(state: TimerState) {
  return {
    customer_ref: state.customer?.id ?? null,
    customer_name: state.customer?.display_name ?? null,
    project_ref: state.project?.id ?? null,
    project_name: state.project?.display_name ?? null,
    service_ref: state.service?.id ?? null,
    service_name: state.service?.display_name ?? null,
    description: state.description || null,
    is_running: state.runningSince !== null,
  }
}

/**
 * Applies authoritative timer fields returned by the server after sync.
 * @param session Updated timer session from the API.
 * @returns Local timer state aligned with server values.
 */
function applyServerSession(session: TimeTrackerSession): TimerState {
  return sessionToState(session)
}

/**
 * Timer tracking with server persistence and QBO picker lazy loads.
 * @param enabled When false, skips initial load until the user is authenticated.
 * @returns Timer state, picker handlers, and log/discard actions.
 */
export function useTimeTracker(enabled = true) {
  const { locale, t } = useLocale()
  const { message, clearMessage, showError, showSuccess } = useFlashMessage()
  const [state, setState] = useState<TimerState>(emptyTimerState)
  const [loading, setLoading] = useState(enabled)
  const [elapsedSeconds, setElapsedSeconds] = useState(0)
  const stateRef = useRef(state)
  stateRef.current = state

  const syncToServer = useCallback(
    async (nextState: TimerState) => {
      try {
        const session = await updateTimeTracker(stateToPayload(nextState))
        const syncedState = applyServerSession(session)
        setState(syncedState)
        setElapsedSeconds(session.elapsed_seconds)
      } catch (caught) {
        showError(getApiErrorMessage(caught, t('timesheet.syncFailed'), locale))
      }
    },
    [locale, showError, t],
  )

  useEffect(() => {
    if (!enabled) {
      setLoading(false)

      return
    }

    let cancelled = false

    const load = async () => {
      setLoading(true)

      try {
        const session = await fetchTimeTracker()
        if (!cancelled) {
          const nextState = session ? sessionToState(session) : emptyTimerState()
          setState(nextState)
          setElapsedSeconds(session?.elapsed_seconds ?? 0)
        }
      } catch (caught) {
        if (!cancelled) {
          showError(getApiErrorMessage(caught, t('timesheet.loadFailed'), locale))
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void load()

    return () => {
      cancelled = true
    }
  }, [enabled, locale, showError, t])

  useEffect(() => {
    if (!state.runningSince) {
      setElapsedSeconds(state.accumulatedSeconds)

      return
    }

    const tick = () => {
      setElapsedSeconds(
        computeElapsedSeconds(stateRef.current.accumulatedSeconds, stateRef.current.runningSince),
      )
    }

    tick()
    const intervalId = window.setInterval(tick, 1000)

    return () => {
      window.clearInterval(intervalId)
    }
  }, [state.accumulatedSeconds, state.runningSince])

  const applyState = useCallback(
    (updater: (current: TimerState) => TimerState, sync = true) => {
      setState((current) => {
        const nextState = updater(current)
        if (sync) {
          void syncToServer(nextState)
        }

        return nextState
      })
    },
    [syncToServer],
  )

  const onCustomerChange = useCallback(
    (customer: QboPickerOption | null) => {
      applyState((current) => ({
        ...current,
        customer,
        project: null,
      }))
    },
    [applyState],
  )

  const onProjectChange = useCallback(
    (project: QboPickerOption | null) => {
      applyState((current) => ({ ...current, project }))
    },
    [applyState],
  )

  const onServiceChange = useCallback(
    (service: QboPickerOption | null) => {
      applyState((current) => ({ ...current, service }))
    },
    [applyState],
  )

  const onDescriptionChange = useCallback((description: string) => {
    setState((current) => ({ ...current, description }))
  }, [])

  const onDescriptionBlur = useCallback(() => {
    void syncToServer(stateRef.current)
  }, [syncToServer])

  const onToggleTimer = useCallback(() => {
    applyState((current) => ({
      ...current,
      runningSince: current.runningSince ? null : new Date().toISOString(),
    }))
  }, [applyState])

  const pickerAvailability = useTimerPickerAvailability({
    enabled,
    loading,
    customer: state.customer,
    project: state.project,
    service: state.service,
    onError: showError,
    loadCustomersFailedMessage: t('timesheet.loadCustomersFailed'),
    loadProjectsFailedMessage: t('timesheet.loadProjectsFailed'),
    loadServicesFailedMessage: t('timesheet.loadServicesFailed'),
    locale,
  })

  useEffect(() => {
    if (pickerAvailability.isCustomerAllowed || state.customer === null) {
      return
    }

    applyState((current) => ({
      ...current,
      customer: null,
      project: null,
    }))
  }, [applyState, pickerAvailability.isCustomerAllowed, state.customer])

  useEffect(() => {
    if (pickerAvailability.isProjectAllowed || state.project === null) {
      return
    }

    applyState((current) => ({
      ...current,
      project: null,
    }))
  }, [applyState, pickerAvailability.isProjectAllowed, state.project])

  useEffect(() => {
    if (pickerAvailability.isServiceAllowed || state.service === null) {
      return
    }

    applyState((current) => ({
      ...current,
      service: null,
    }))
  }, [applyState, pickerAvailability.isServiceAllowed, state.service])

  const fetchCustomers = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchQboCustomers({ refresh, signal }),
    [],
  )

  const fetchProjects = useCallback(
    (refresh: boolean, signal: AbortSignal) => {
      const customerRef = stateRef.current.customer?.id
      if (!customerRef) {
        return Promise.resolve([])
      }

      return fetchQboProjects(customerRef, { refresh, signal })
    },
    [],
  )

  const fetchServices = useCallback(
    (refresh: boolean, signal: AbortSignal) => fetchQboServices({ refresh, signal }),
    [],
  )

  const customersSelect = useLazyApiSelect({
    enabled: enabled && !loading && pickerAvailability.showCustomerPicker,
    fetch: fetchCustomers,
    onError: (errorMessage) => showError(errorMessage),
    errorMessage: t('timesheet.loadCustomersFailed'),
    seedItems: pickerAvailability.customers,
  })

  const projectsSelect = useLazyApiSelect({
    enabled: enabled && !loading && pickerAvailability.showProjectPicker,
    fetch: fetchProjects,
    onError: (errorMessage) => showError(errorMessage),
    errorMessage: t('timesheet.loadProjectsFailed'),
    seedItems: pickerAvailability.projects,
  })

  const servicesSelect = useLazyApiSelect({
    enabled: enabled && !loading && pickerAvailability.showServicePicker,
    fetch: fetchServices,
    onError: (errorMessage) => showError(errorMessage),
    errorMessage: t('timesheet.loadServicesFailed'),
    seedItems: pickerAvailability.services,
  })

  const { run: onLogTime, pending: logging } = useGuardedAction(async () => {
    clearMessage()

    try {
      await logTimeTracker()
      const reset = emptyTimerState()
      setState(reset)
      setElapsedSeconds(0)
      showSuccess(t('timesheet.savedSuccess'))
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('timesheet.saveFailed'), locale))
    }
  })

  const { run: onDiscard, pending: discarding } = useGuardedAction(async () => {
    clearMessage()

    try {
      await discardTimeTracker()
      const reset = emptyTimerState()
      setState(reset)
      setElapsedSeconds(0)
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('timesheet.discardFailed'), locale))
    }
  })

  const isRunning = state.runningSince !== null
  const headerLabel = state.project?.display_name ?? state.customer?.display_name ?? t('timesheet.noClient')

  return {
    loading,
    state,
    elapsedSeconds,
    isRunning,
    headerLabel,
    message,
    logging,
    discarding,
    customersSelect,
    projectsSelect,
    servicesSelect,
    showCustomerPicker: pickerAvailability.showCustomerPicker,
    showProjectPicker: pickerAvailability.showProjectPicker,
    showServicePicker: pickerAvailability.showServicePicker,
    onCustomerChange,
    onProjectChange,
    onServiceChange,
    onDescriptionChange,
    onDescriptionBlur,
    onToggleTimer,
    onLogTime,
    onDiscard,
  }
}
