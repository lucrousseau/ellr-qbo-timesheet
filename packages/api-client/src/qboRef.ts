/**
 * @file QuickBooks reference normalization helpers.
 */

/**
 * Normalizes QuickBooks entity identifiers for stable comparisons.
 * @param id Raw QuickBooks identifier from the API or UI.
 * @returns Numeric QuickBooks identifier when available.
 */
export function normalizeQboRef(id: string | number): string {
  const digits = String(id).replace(/\D/g, '')

  return digits !== '' ? digits : String(id)
}

/**
 * Returns whether two QuickBooks identifiers refer to the same entity.
 * @param left First identifier.
 * @param right Second identifier.
 * @returns True when both identifiers normalize to the same value.
 */
export function qboRefsMatch(left: string | number, right: string | number): boolean {
  return normalizeQboRef(left) === normalizeQboRef(right)
}
