/**
 * @file Persistent timer state and QBO picker orchestration for the timesheet app.
 */

import { useCallback, useEffect, useRef, useState } from 'react'
import {
  DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
  discardTimeTracker,
  fetchAppConfig,
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
import { capElapsedSeconds, computeElapsedSeconds } from '../utils/timerFormat'
import { createSyncQueue } from '../utils/timerSyncQueue'

export type { QboPickerOption } from '@ellr/api-client'

type TimerState = {
  customer: QboPickerOption | null
  project: QboPickerOption | null
  service: QboPickerOption | null
  description: string
  ticketKey: string
  ticketSource: 'manual' | 'jira' | 'linear' | null
  ticketUrl: string | null
  ticketTitle: string | null
  isBillable: boolean
  accumulatedSeconds: number
  runningSince: string | null
}

type UseTimeTrackerOptions = {
  maxAccumulatedSeconds?: number
}

const emptyTimerState = (): TimerState => ({
  customer: null,
  project: null,
  service: null,
  description: '',
  ticketKey: '',
  ticketSource: null,
  ticketUrl: null,
  ticketTitle: null,
  isBillable: false,
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
    ticketKey: session.ticket_key ?? '',
    ticketSource: session.ticket_source,
    ticketUrl: session.ticket_url,
    ticketTitle: session.ticket_title,
    isBillable: session.is_billable,
    accumulatedSeconds: session.accumulated_seconds,
    runningSince: session.is_running ? session.running_since : null,
  }
}

/**
 * Builds the API payload from local timer state.
 * @param state Local timer state.
 * @returns Payload for PUT /time-tracker.
 */
function stateToPayload(state: TimerState, options?: { accumulatedSeconds?: number }) {
  return {
    customer_ref: state.customer?.id ?? null,
    project_ref: state.project?.id ?? null,
    service_ref: state.service?.id ?? null,
    description: state.description || null,
    ticket_key: state.ticketKey || null,
    ticket_source: state.ticketKey
      ? (state.ticketSource ?? 'manual')
      : null,
    ticket_url: state.ticketKey ? state.ticketUrl : null,
    ticket_title: state.ticketKey ? state.ticketTitle : null,
    is_billable: state.isBillable,
    is_running: state.runningSince !== null,
    ...(options?.accumulatedSeconds !== undefined
      ? { accumulated_seconds: options.accumulatedSeconds }
      : {}),
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
 * @param options Optional timer limits from public app config.
 * @returns Timer state, picker handlers, and log/discard actions.
 */
export function useTimeTracker(enabled = true, options: UseTimeTrackerOptions = {}) {
  const { locale, t } = useLocale()
  const { message, clearMessage, showError, showSuccess } = useFlashMessage()
  const [state, setState] = useState<TimerState>(emptyTimerState)
  const [loading, setLoading] = useState(enabled)
  const [elapsedSeconds, setElapsedSeconds] = useState(0)
  const [entriesRefreshToken, setEntriesRefreshToken] = useState(0)
  const [maxAccumulatedSeconds, setMaxAccumulatedSeconds] = useState(
    options.maxAccumulatedSeconds ?? DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
  )
  const stateRef = useRef(state)
  const syncQueueRef = useRef(createSyncQueue())
  stateRef.current = state

  useEffect(() => {
    if (options.maxAccumulatedSeconds !== undefined) {
      setMaxAccumulatedSeconds(options.maxAccumulatedSeconds)
    }
  }, [options.maxAccumulatedSeconds])

  useEffect(() => {
    if (!enabled) {
      return
    }

    let cancelled = false

    void fetchAppConfig()
      .then((config) => {
        if (!cancelled) {
          setMaxAccumulatedSeconds(config.time_tracker_max_accumulated_seconds)
        }
      })
      .catch(() => {
        // Keep the default cap when health config is unavailable.
      })

    return () => {
      cancelled = true
    }
  }, [enabled])

  const syncToServer = useCallback(
    async (
      nextState: TimerState,
      syncOptions?: { accumulatedSeconds?: number },
    ): Promise<boolean> => {
      return syncQueueRef.current.enqueue(async () => {
        try {
          const session = await updateTimeTracker(stateToPayload(nextState, syncOptions))
          const syncedState = applyServerSession(session)
          stateRef.current = syncedState
          setState(syncedState)
          setElapsedSeconds(session.elapsed_seconds)

          return true
        } catch (caught) {
          showError(getApiErrorMessage(caught, t('timesheet.syncFailed'), locale))

          return false
        }
      })
    },
    [locale, showError, t],
  )

  const restoreTimerState = useCallback((previousState: TimerState, previousElapsed: number) => {
    stateRef.current = previousState
    setState(previousState)
    setElapsedSeconds(previousElapsed)
  }, [])

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
          stateRef.current = nextState
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
        stateRef.current = nextState
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
    setState((current) => {
      const nextState = { ...current, description }
      stateRef.current = nextState

      return nextState
    })
  }, [])

  const onDescriptionBlur = useCallback(() => {
    void syncToServer(stateRef.current)
  }, [syncToServer])

  const onTicketKeyChange = useCallback((ticketKey: string) => {
    setState((current) => {
      const nextState: TimerState = {
        ...current,
        ticketKey,
        ticketSource: ticketKey.trim() ? (current.ticketSource ?? 'manual') : null,
        ticketUrl: ticketKey.trim() ? current.ticketUrl : null,
        ticketTitle: ticketKey.trim() ? current.ticketTitle : null,
      }
      stateRef.current = nextState

      return nextState
    })
  }, [])

  const onTicketKeyBlur = useCallback(() => {
    void syncToServer(stateRef.current)
  }, [syncToServer])

  const onBillableChange = useCallback(
    (isBillable: boolean) => {
      applyState((current) => ({ ...current, isBillable }))
    },
    [applyState],
  )

  const commitElapsedSeconds = useCallback(
    async (seconds: number): Promise<boolean> => {
      const cappedSeconds = capElapsedSeconds(seconds, maxAccumulatedSeconds)
      const previousState = stateRef.current
      const previousElapsed = computeElapsedSeconds(
        previousState.accumulatedSeconds,
        previousState.runningSince,
      )
      const nextState: TimerState = {
        ...previousState,
        accumulatedSeconds: cappedSeconds,
        runningSince: previousState.runningSince ? new Date().toISOString() : null,
      }

      stateRef.current = nextState
      setState(nextState)
      setElapsedSeconds(cappedSeconds)

      const synced = await syncToServer(nextState, { accumulatedSeconds: cappedSeconds })

      if (!synced) {
        restoreTimerState(previousState, previousElapsed)
      }

      return synced
    },
    [maxAccumulatedSeconds, restoreTimerState, syncToServer],
  )

  const onElapsedChange = useCallback(
    (seconds: number) => {
      void commitElapsedSeconds(seconds)
    },
    [commitElapsedSeconds],
  )

  const onToggleTimer = useCallback(async () => {
    const current = stateRef.current
    const displayElapsed = capElapsedSeconds(
      computeElapsedSeconds(current.accumulatedSeconds, current.runningSince),
      maxAccumulatedSeconds,
    )

    if (current.runningSince) {
      const nextState: TimerState = {
        ...current,
        accumulatedSeconds: displayElapsed,
        runningSince: null,
      }
      const previousElapsed = computeElapsedSeconds(
        current.accumulatedSeconds,
        current.runningSince,
      )
      stateRef.current = nextState
      setState(nextState)
      setElapsedSeconds(displayElapsed)

      const synced = await syncToServer(nextState, { accumulatedSeconds: displayElapsed })
      if (!synced) {
        restoreTimerState(current, previousElapsed)
      }

      return
    }

    const nextState: TimerState = {
      ...current,
      accumulatedSeconds: displayElapsed,
      runningSince: new Date().toISOString(),
    }
    const previousElapsed = computeElapsedSeconds(
      current.accumulatedSeconds,
      current.runningSince,
    )
    stateRef.current = nextState
    setState(nextState)
    setElapsedSeconds(displayElapsed)

    const synced = await syncToServer(nextState, { accumulatedSeconds: displayElapsed })
    if (!synced) {
      restoreTimerState(current, previousElapsed)
    }
  }, [maxAccumulatedSeconds, restoreTimerState, syncToServer])

  const pickerAvailability = useTimerPickerAvailability({
    enabled,
    loading,
    customer: state.customer,
    project: state.project,
    service: state.service,
    onError: showError,
    loadCustomersFailedMessage: t('timesheet.loadCustomersFailed'),
    loadProjectsFailedMessage: t('timesheet.loadProjectsFailed'),
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
    if (!enabled || loading || pickerAvailability.customersStatus !== 'empty') {
      return
    }

    setState((current) => {
      if (current.runningSince === null) {
        return current
      }

      const nextState = { ...current, runningSince: null }
      stateRef.current = nextState
      void syncToServer(nextState)

      return nextState
    })
  }, [enabled, loading, pickerAvailability.customersStatus, syncToServer])

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
  })

  const { run: onLogTime, pending: logging } = useGuardedAction(async () => {
    clearMessage()

    const current = stateRef.current
    if (current.runningSince) {
      const displayElapsed = capElapsedSeconds(
        computeElapsedSeconds(current.accumulatedSeconds, current.runningSince),
        maxAccumulatedSeconds,
      )
      const pausedState: TimerState = {
        ...current,
        accumulatedSeconds: displayElapsed,
        runningSince: null,
      }
      const previousElapsed = displayElapsed
      const synced = await syncToServer(pausedState, { accumulatedSeconds: displayElapsed })

      if (!synced) {
        restoreTimerState(current, previousElapsed)

        return
      }
    }

    try {
      await logTimeTracker()
      const reset = emptyTimerState()
      stateRef.current = reset
      setState(reset)
      setElapsedSeconds(0)
      setEntriesRefreshToken((current) => current + 1)
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
      stateRef.current = reset
      setState(reset)
      setElapsedSeconds(0)
    } catch (caught) {
      showError(getApiErrorMessage(caught, t('timesheet.discardFailed'), locale))
    }
  })

  const isRunning = state.runningSince !== null
  const headerLabel = state.project?.display_name ?? state.customer?.display_name ?? t('timesheet.noClient')
  const canTrackTime =
    pickerAvailability.customersStatus === 'available' ||
    (pickerAvailability.customersStatus === 'error' && state.customer !== null)

  const hasDraftSession =
    elapsedSeconds > 0 ||
    state.description.trim() !== '' ||
    state.ticketKey.trim() !== '' ||
    state.customer !== null ||
    state.project !== null ||
    state.service !== null ||
    state.isBillable

  return {
    loading,
    state,
    elapsedSeconds,
    maxAccumulatedSeconds,
    isRunning,
    headerLabel,
    canTrackTime,
    hasDraftSession,
    customersStatus: pickerAvailability.customersStatus,
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
    onTicketKeyChange,
    onTicketKeyBlur,
    onBillableChange,
    onToggleTimer,
    onElapsedChange,
    commitElapsedSeconds,
    onLogTime,
    onDiscard,
    entriesRefreshToken,
  }
}
