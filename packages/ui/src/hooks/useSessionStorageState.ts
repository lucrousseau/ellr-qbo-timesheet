/**
 * @file React hook that mirrors useState while persisting values in sessionStorage.
 */

import { useCallback, useEffect, useState } from 'react'

type UseSessionStorageStateOptions<T extends string> = {
  isValid?: (value: string) => value is T
}

/**
 * Reads a string value from sessionStorage when available.
 * @param key Storage key.
 * @param defaultValue Fallback when storage is empty or unavailable.
 * @param isValid Optional guard rejecting unknown stored values.
 * @returns Stored value or default.
 */
function readSessionStorageValue<T extends string>(
  key: string,
  defaultValue: T,
  isValid?: (value: string) => value is T,
): T {
  if (typeof sessionStorage === 'undefined') {
    return defaultValue
  }

  try {
    const stored = sessionStorage.getItem(key)
    if (stored === null) {
      return defaultValue
    }

    if (isValid && !isValid(stored)) {
      try {
        sessionStorage.setItem(key, defaultValue)
      } catch {
        // Ignore quota or privacy-mode failures.
      }

      return defaultValue
    }

    return stored as T
  } catch {
    return defaultValue
  }
}

/**
 * Persists string state in sessionStorage across reloads within the same tab.
 * @param key sessionStorage key.
 * @param defaultValue Initial value when no entry exists.
 * @param options.isValid Optional guard rejecting unknown stored values.
 * @returns State tuple compatible with useState.
 */
export function useSessionStorageState<T extends string>(
  key: string,
  defaultValue: T,
  options: UseSessionStorageStateOptions<T> = {},
): [T, (value: T) => void] {
  const { isValid } = options

  const [state, setState] = useState<T>(() => readSessionStorageValue(key, defaultValue, isValid))

  useEffect(() => {
    setState(readSessionStorageValue(key, defaultValue, isValid))
  }, [defaultValue, isValid, key])

  const setPersistedState = useCallback(
    (value: T) => {
      setState(value)
      try {
        sessionStorage.setItem(key, value)
      } catch {
        // Ignore quota or privacy-mode failures.
      }
    },
    [key],
  )

  return [state, setPersistedState]
}
