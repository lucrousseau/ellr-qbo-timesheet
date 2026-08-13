/**
 * @file Approval status labels and draft actionability helpers for time entries.
 */

import type { TimeActivityRow } from '@ellr/api-client'

/**
 * Maps a time entry approval status to a localized label.
 * @param status Approval status from the API row.
 * @param t Locale lookup function.
 * @returns Localized status label.
 */
export function formatApprovalStatus(
  status: TimeActivityRow['approvalStatus'],
  t: (key: string) => string,
): string {
  if (status === 'draft') {
    return t('timeActivity.statusDraft')
  }

  if (status === 'pending') {
    return t('timeActivity.statusPending')
  }

  if (status === 'approved') {
    return t('timeActivity.statusApproved')
  }

  if (status === 'rejected') {
    return t('timeActivity.statusRejected')
  }

  return t('timeActivity.noValue')
}

/**
 * Returns whether an entry can be edited or submitted by the employee.
 * @param status Approval status from the API row.
 * @returns True for draft and rejected statuses.
 */
export function isDraftActionable(status: TimeActivityRow['approvalStatus']): boolean {
  return status === 'draft' || status === 'rejected'
}
