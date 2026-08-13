/**
 * @file Helpers to hydrate QBO picker options from time entry list rows.
 */

import type { QboPickerOption } from '@ellr/api-client'

/**
 * Builds a picker option from a stored ref and display label.
 * @param ref QuickBooks entity ref.
 * @param label Display label fallback.
 * @returns Picker option or null.
 */
export function optionFromRef(
  ref: string | null | undefined,
  label: string | null | undefined,
): QboPickerOption | null {
  if (!ref) {
    return null
  }

  return { id: ref, display_name: label?.trim() ? label : ref }
}

/**
 * Splits a combined client/project table label into picker labels.
 * @param customerName Combined `Client / project` display value from list rows.
 * @returns Separate customer and project labels when both are present.
 */
export function splitCustomerProjectLabel(customerName: string | null | undefined): {
  customerLabel: string | null
  projectLabel: string | null
} {
  if (!customerName?.trim()) {
    return { customerLabel: null, projectLabel: null }
  }

  const separator = ' / '
  const index = customerName.indexOf(separator)
  if (index === -1) {
    return { customerLabel: customerName, projectLabel: null }
  }

  return {
    customerLabel: customerName.slice(0, index),
    projectLabel: customerName.slice(index + separator.length),
  }
}
