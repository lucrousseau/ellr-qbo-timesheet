/**
 * @file Triggers lazy option loading when a combobox panel opens.
 */

import { useEffect, useRef } from 'react'

/**
 * Calls `onLoad` when the combobox transitions from closed to open.
 * @param open Whether the combobox panel is open.
 * @param onLoad Fetch callback for the open transition.
 */
export function useLoadOnOpen(open: boolean, onLoad: () => void | Promise<void>) {
  const wasOpenRef = useRef(false)

  useEffect(() => {
    if (open && !wasOpenRef.current) {
      void onLoad()
    }

    wasOpenRef.current = open
  }, [onLoad, open])
}
