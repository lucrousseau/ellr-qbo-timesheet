/**
 * @file Detects fetch rejections caused by `AbortSignal` cancellation.
 */

/**
 * Returns whether an error was raised by an aborted fetch.
 * @param error Caught rejection from a guarded fetch.
 * @returns `true` when the request was intentionally cancelled.
 */
export function isAbortError(error: unknown): boolean {
  if (error instanceof DOMException && error.name === 'AbortError') {
    return true
  }

  return (
    typeof error === 'object' &&
    error !== null &&
    'name' in error &&
    (error as { name: string }).name === 'AbortError'
  )
}
