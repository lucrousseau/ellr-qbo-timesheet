/**
 * @file Prevents duplicate invocations of click and submit handlers.
 */

import { useCallback, useRef, useState } from 'react'

type UseGuardedActionOptions = {
  /** When true, `pending` stays true after a successful run (e.g. OAuth redirect). */
  keepPendingOnSuccess?: boolean
}

/**
 * Wraps an action so only one invocation runs at a time until it settles.
 * Uses a ref guard for the first duplicate click before React re-renders `pending`.
 * @param action Handler to protect (sync or async).
 * @param options Optional behavior flags.
 * @returns Guarded runner and pending flag for disabling UI controls.
 */
export function useGuardedAction<TArgs extends unknown[], TResult>(
  action: (...args: TArgs) => TResult | Promise<TResult>,
  options: UseGuardedActionOptions = {},
) {
  const { keepPendingOnSuccess = false } = options
  const pendingRef = useRef(false)
  const [pending, setPending] = useState(false)
  const actionRef = useRef(action)
  actionRef.current = action

  const releasePending = useCallback(() => {
    pendingRef.current = false
    setPending(false)
  }, [])

  const run = useCallback(
    async (...args: TArgs): Promise<TResult | undefined> => {
      if (pendingRef.current) {
        return undefined
      }

      pendingRef.current = true
      setPending(true)

      try {
        return await actionRef.current(...args)
      } catch (caught) {
        releasePending()
        throw caught
      } finally {
        if (!keepPendingOnSuccess) {
          releasePending()
        }
      }
    },
    [keepPendingOnSuccess, releasePending],
  )

  return { run, pending }
}
