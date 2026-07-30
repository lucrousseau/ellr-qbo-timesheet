/**
 * @file Warning when a timesheet user has no clients available for time tracking.
 */

import { Alert, Button, useLocale } from '@ellr/ui'

type AssignedClientsWarningProps = {
  allCustomersAccess: boolean
  hasDraftSession?: boolean
  discarding?: boolean
  onDiscard?: () => void
}

/**
 * Shown when an employee cannot select a client and must not track time.
 * @param props Whether the user has all-customers access or assigned clients only.
 * @returns Warning alert block.
 */
export function AssignedClientsWarning({
  allCustomersAccess,
  hasDraftSession = false,
  discarding = false,
  onDiscard,
}: AssignedClientsWarningProps) {
  const { t } = useLocale()

  return (
    <div className="space-y-4">
      <Alert variant="warning">
        {allCustomersAccess ? t('timesheet.noQboClientsWarning') : t('timesheet.assignedClientsWarning')}
      </Alert>
      {hasDraftSession && onDiscard ? (
        <div>
          <p className="mb-3 text-sm text-slate-600">{t('timesheet.blockedDraftHelp')}</p>
          <Button variant="secondary" onClick={onDiscard} disabled={discarding}>
            {discarding ? t('timesheet.discardingDraft') : t('timesheet.discardDraft')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
