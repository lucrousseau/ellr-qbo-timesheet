/**
 * @file Warning when the signed-in user has no QBO employee linked.
 */

import { Alert } from '@ellr/ui'

/**
 * Shown when an administrator has not mapped the account to a QuickBooks employee.
 * @returns Warning alert block.
 */
export function QboEmployeeWarning() {
  return (
    <Alert variant="warning">
      QuickBooks employee not configured. An administrator must link your account to a QBO employee.
    </Alert>
  )
}
