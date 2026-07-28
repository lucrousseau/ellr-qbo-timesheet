/**
 * @file Value comparison helpers for Headless UI select components.
 */

/**
 * Builds a comparator for object options keyed by a stable string id.
 * @param getOptionValue Returns the unique option id.
 * @returns Comparator safe for nullable Headless UI values.
 */
export function compareOptionBy<T>(getOptionValue: (option: T) => string) {
  return (left: T | null | undefined, right: T | null | undefined) => {
    if (left == null || right == null) {
      return left === right
    }

    return getOptionValue(left) === getOptionValue(right)
  }
}
