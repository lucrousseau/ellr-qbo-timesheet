/**
 * @file Tenant user permission levels shared with the Laravel API.
 */

/** Stable permission codes returned by the API (labels live in UI i18n). */
export type UserLevel = 'employee' | 'lead' | 'manager'

/** Ordered list of supported permission levels (lowest first). */
export const USER_LEVELS: readonly UserLevel[] = ['employee', 'lead', 'manager']

/** Default level assigned to newly provisioned users. */
export const DEFAULT_USER_LEVEL: UserLevel = 'employee'

/**
 * Normalizes an API or user value to a supported permission level.
 * @param value Raw level from the API or UI.
 * @returns Supported level, defaulting to the lowest tier.
 */
export function normalizeUserLevel(value: string | null | undefined): UserLevel {
  return USER_LEVELS.includes(value as UserLevel) ? (value as UserLevel) : DEFAULT_USER_LEVEL
}
