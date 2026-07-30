/**
 * @file Reusable IMask presets for duration-style inputs.
 */

import IMask, { type FactoryOpts } from 'imask'

/** Default cap aligned with the Laravel API (24 hours). */
export const DEFAULT_DURATION_MAX_SECONDS = 86_400

/**
 * Builds an HH:MM mask capped to the server maximum accumulated seconds.
 * @param maxAccumulatedSeconds Maximum elapsed seconds allowed by the API.
 * @returns IMask options for duration entry.
 */
export function createDurationHoursMinutesMask(
  maxAccumulatedSeconds: number = DEFAULT_DURATION_MAX_SECONDS,
): FactoryOpts {
  const safeMax = Math.max(60, Math.floor(maxAccumulatedSeconds))
  const maxHours = Math.floor(safeMax / 3600)

  return {
    mask: 'HH:MM',
    lazy: false,
    overwrite: true,
    autofix: true,
    blocks: {
      HH: {
        mask: IMask.MaskedRange,
        from: 0,
        to: maxHours,
        maxLength: 2,
      },
      MM: {
        mask: IMask.MaskedRange,
        from: 0,
        to: 59,
        maxLength: 2,
      },
    },
    commit: (value) => capDurationMaskValue(String(value), safeMax),
  }
}

/**
 * Caps a masked HH:MM value to the configured maximum duration.
 * @param value Masked duration text.
 * @param maxAccumulatedSeconds Maximum elapsed seconds allowed by the API.
 * @returns Capped HH:MM string.
 */
export function capDurationMaskValue(value: string, maxAccumulatedSeconds: number): string {
  const parts = value.split(':')
  if (parts.length !== 2) {
    return value
  }

  const hours = Number(parts[0])
  const minutes = Number(parts[1])

  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
    return value
  }

  const totalSeconds = hours * 3600 + minutes * 60
  const capped = Math.min(Math.max(0, totalSeconds), maxAccumulatedSeconds)
  const cappedHours = Math.floor(capped / 3600)
  const cappedMinutes = Math.floor((capped % 3600) / 60)

  return `${String(cappedHours).padStart(2, '0')}:${String(cappedMinutes).padStart(2, '0')}`
}

/**
 * HH:MM duration mask using the default API cap (24 hours).
 */
export const durationHoursMinutesMask = createDurationHoursMinutesMask()
