/**
 * @file Error state when QuickBooks client lists cannot be loaded for time tracking.
 */

import { Alert, Button, useLocale } from '@ellr/ui'

type CustomerLoadErrorProps = {
  hasDraftSession?: boolean
  discarding?: boolean
  onDiscard?: () => void
}

/**
 * Shown when client availability cannot be loaded and the timer must stay hidden.
 * @param props Optional draft session discard controls.
 * @returns Error alert block.
 */
export function CustomerLoadError({
  hasDraftSession = false,
  discarding = false,
  onDiscard,
}: CustomerLoadErrorProps) {
  const { t } = useLocale()

  return (
    <div className="space-y-4">
      <Alert variant="error">{t('timesheet.loadCustomersFailed')}</Alert>
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
