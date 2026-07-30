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
 * Formats elapsed seconds as HH:MM for editable timer inputs.
 * @param totalSeconds Elapsed time in whole seconds.
 * @returns Colon-separated hours and minutes.
 */
export function formatElapsedInput(totalSeconds: number): string {
  const { hours, minutes } = formatElapsedSeconds(totalSeconds)

  return `${hours}:${minutes}`
}

/**
 * Caps elapsed seconds to the API maximum.
 * @param totalSeconds Raw elapsed seconds.
 * @param maxSeconds Maximum allowed accumulated seconds.
 * @returns Capped whole seconds.
 */
export function capElapsedSeconds(totalSeconds: number, maxSeconds: number): number {
  const safeMax = Math.max(1, Math.floor(maxSeconds))

  return Math.min(Math.max(0, Math.floor(totalSeconds)), safeMax)
}

/**
 * Parses HH:MM timer input into total seconds.
 * @param value User-entered timer text.
 * @param maxSeconds Optional API maximum to cap parsed values.
 * @returns Total seconds, or null when the value is invalid.
 */
export function parseElapsedInput(value: string, maxSeconds?: number): number | null {
  const trimmed = value.trim()
  if (trimmed === '') {
    return 0
  }

  const parts = trimmed.split(':').map((part) => part.trim())
  if (parts.length !== 2) {
    return null
  }

  if (!parts.every((part) => /^\d{1,2}$/.test(part))) {
    return null
  }

  const [hoursPart, minutesPart] = parts
  const hours = Number(hoursPart)
  const minutes = Number(minutesPart)

  if (minutes > 59) {
    return null
  }

  const totalSeconds = hours * 3600 + minutes * 60

  if (maxSeconds !== undefined) {
    return capElapsedSeconds(totalSeconds, maxSeconds)
  }

  return totalSeconds
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
