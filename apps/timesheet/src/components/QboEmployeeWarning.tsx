/**
 * @file Warning when the signed-in user has no QBO employee linked.
 */

import { Alert, useLocale } from '@ellr/ui'

/**
 * Shown when an administrator has not mapped the account to a QuickBooks employee.
 * @returns Warning alert block.
 */
export function QboEmployeeWarning() {
  const { t } = useLocale()

  return <Alert variant="warning">{t('timesheet.qboEmployeeWarning')}</Alert>
}
