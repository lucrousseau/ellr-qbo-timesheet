import { useCallback, useState } from 'react'

export type FlashMessage = {
  text: string
  type: 'success' | 'error' | 'warning'
}

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
