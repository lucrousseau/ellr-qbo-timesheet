/**
 * @file React hook for transient success and error banner messages.
 */

import { useCallback, useState } from 'react'

/**
 * Temporary flash message shown after a user action.
 */
export type FlashMessage = {
  text: string
  type: 'success' | 'error' | 'warning'
}

/**
 * State and helpers for flash messages (success, error, warning).
 * @returns Current message and `show*` / `clearMessage` helpers.
 */
export function useFlashMessage() {
  const [message, setMessage] = useState<FlashMessage | null>(null)

  const clearMessage = useCallback(() => setMessage(null), [])

  const showError = useCallback((text: string) => setMessage({ text, type: 'error' }), [])
  const showSuccess = useCallback((text: string) => setMessage({ text, type: 'success' }), [])
  const showWarning = useCallback((text: string) => setMessage({ text, type: 'warning' }), [])

  return {
    message,
    setMessage,
    clearMessage,
    showError,
    showSuccess,
    showWarning,
  }
}
