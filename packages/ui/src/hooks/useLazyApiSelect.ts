/**
 * @file Lazy-loaded options for API-backed select controls.
 */

import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage } from '../i18n/apiErrorMessages'
import { useLocale } from '../i18n/LocaleProvider'
import { isAbortError } from '../utils/isAbortError'

type UseLazyApiSelectOptions<T> = {
  enabled: boolean
  fetch: (refresh: boolean, signal: AbortSignal) => Promise<T[]>
  onError: (message: string) => void
  errorMessage: string
}

/**
 * Loads select options on user interaction; clears the list when the panel closes.
 * Each open triggers a refresh request; an in-flight request is aborted on close or reopen.
 * @param options Fetch callback, enable flag, and error handling.
 * @returns Options, loading state, and open/close handlers for the select control.
 */
export function useLazyApiSelect<T>({
  enabled,
  fetch,
  onError,
  errorMessage,
}: UseLazyApiSelectOptions<T>) {
  const { locale } = useLocale()
  const [items, setItems] = useState<T[]>([])
  const [loading, setLoading] = useState(false)
  const [loaded, setLoaded] = useState(false)
  const abortControllerRef = useRef<AbortController | null>(null)
  const requestIdRef = useRef(0)

  const cancelInFlight = useCallback(() => {
    abortControllerRef.current?.abort()
    abortControllerRef.current = null
  }, [])

  useEffect(() => {
    if (!enabled) {
      cancelInFlight()
      setItems([])
      setLoaded(false)
      setLoading(false)
    }
  }, [cancelInFlight, enabled])

  useEffect(() => {
    return () => {
      cancelInFlight()
    }
  }, [cancelInFlight])

  const onOpen = useCallback(async () => {
    if (!enabled) {
      return
    }

    cancelInFlight()
    const requestId = ++requestIdRef.current
    const controller = new AbortController()
    abortControllerRef.current = controller

    setLoading(true)
    setItems([])
    setLoaded(false)

    try {
      const nextItems = await fetch(true, controller.signal)

      if (requestId !== requestIdRef.current) {
        return
      }

      setItems(nextItems)
      setLoaded(true)
    } catch (caught) {
      if (isAbortError(caught) || requestId !== requestIdRef.current) {
        return
      }

      setItems([])
      setLoaded(true)
      onError(getApiErrorMessage(caught, errorMessage, locale))
    } finally {
      if (requestId === requestIdRef.current) {
        abortControllerRef.current = null
        setLoading(false)
      }
    }
  }, [cancelInFlight, enabled, errorMessage, fetch, locale, onError])

  const onClose = useCallback(() => {
    requestIdRef.current += 1
    cancelInFlight()
    setItems([])
    setLoaded(false)
    setLoading(false)
  }, [cancelInFlight])

  return { items, loading, loaded, onOpen, onClose }
}
