/**
 * @file QuickBooks connection status and OAuth actions for administrators.
 */

import { useState } from 'react'
import {
  Button,
  cardClass,
  sectionTitleClass,
  sectionHelpClass,
  ConfirmDialog,
  useLocale,
} from '@ellr/ui'

type QuickBooksConnectionStatus = {
  connected: boolean
  realm_id?: string
  refresh_token_expired?: boolean
  needs_refresh?: boolean
}

type QuickBooksConnectionPanelProps = {
  status: QuickBooksConnectionStatus | null
  connecting: boolean
  disconnecting: boolean
  onConnect: () => void
  onDisconnect: () => void
}

/**
 * Admin dashboard section for QuickBooks OAuth connection.
 * @param props Panel state and event handlers from `useQuickBooksAdmin`.
 * @returns QuickBooks connection card content.
 */
export function QuickBooksConnectionPanel({
  status,
  connecting,
  disconnecting,
  onConnect,
  onDisconnect,
}: QuickBooksConnectionPanelProps) {
  const { t } = useLocale()
  const [disconnectDialogOpen, setDisconnectDialogOpen] = useState(false)

  const statusLabel = (() => {
    if (!status) {
      return null
    }

    if (status.connected && status.needs_refresh) {
      return t('admin.statusConnectedRefreshPending', { realmId: status.realm_id ?? '' })
    }

    if (status.connected) {
      return t('admin.statusConnected', { realmId: status.realm_id ?? '' })
    }

    if (status.refresh_token_expired) {
      return t('admin.statusExpired')
    }

    return t('admin.statusNotConnected')
  })()

  const handleConfirmDisconnect = () => {
    setDisconnectDialogOpen(false)
    void onDisconnect()
  }

  return (
    <section className={cardClass}>
      <h2 className={sectionTitleClass}>{t('admin.quickbooksTitle')}</h2>
      <p className={sectionHelpClass}>{t('admin.quickbooksConnectionHelp')}</p>

      {statusLabel && (
        <p className="mt-4 text-sm text-brand-muted">
          {t('admin.statusLabel')}{' '}
          <span className="font-medium text-brand-primary">{statusLabel}</span>
        </p>
      )}

      <div className="mt-6 flex gap-3">
        <Button type="button" onClick={onConnect} disabled={connecting}>
          {connecting ? t('admin.redirecting') : t('admin.connectQuickBooks')}
        </Button>
        {status?.connected && (
          <Button
            type="button"
            variant="secondary"
            onClick={() => setDisconnectDialogOpen(true)}
            disabled={disconnecting}
          >
            {disconnecting ? t('admin.disconnecting') : t('admin.disconnectQuickBooks')}
          </Button>
        )}
      </div>

      <ConfirmDialog
        open={disconnectDialogOpen}
        title={t('admin.disconnectQuickBooksConfirmTitle')}
        description={t('admin.disconnectQuickBooksConfirmDescription')}
        confirmLabel={t('admin.disconnectQuickBooksConfirmAction')}
        cancelLabel={t('common.cancel')}
        confirming={disconnecting}
        onConfirm={handleConfirmDisconnect}
        onCancel={() => setDisconnectDialogOpen(false)}
      />
    </section>
  )
}
