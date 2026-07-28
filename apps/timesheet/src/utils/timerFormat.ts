/**
 * @file Elapsed time formatting helpers for the timesheet timer.
 */

/**
 * Formats elapsed seconds as HH:MM:SS for the timer display.
 * @param totalSeconds Elapsed time in whole seconds.
 * @returns Hours, minutes, and seconds segments.
 */
export function formatElapsedSeconds(totalSeconds: number): {
  hours: string
  minutes: string
  seconds: string
} {
  const safeSeconds = Math.max(0, Math.floor(totalSeconds))
  const hours = Math.floor(safeSeconds / 3600)
  const minutes = Math.floor((safeSeconds % 3600) / 60)
  const seconds = safeSeconds % 60

  return {
    hours: String(hours).padStart(2, '0'),
    minutes: String(minutes).padStart(2, '0'),
    seconds: String(seconds).padStart(2, '0'),
  }
}

/**
 * Computes total elapsed seconds from accumulated time and an optional running segment.
 * @param accumulatedSeconds Seconds recorded while paused.
 * @param runningSince ISO timestamp when the timer resumed, or null when paused.
 * @returns Total elapsed seconds.
 */
export function computeElapsedSeconds(
  accumulatedSeconds: number,
  runningSince: string | null,
): number {
  let elapsed = Math.max(0, accumulatedSeconds)

  if (runningSince) {
    const startedAt = new Date(runningSince).getTime()
    if (!Number.isNaN(startedAt)) {
      elapsed += Math.max(0, Math.floor((Date.now() - startedAt) / 1000))
    }
  }

  return elapsed
}
