/**
 * @file QuickBooks connection status and OAuth actions for administrators.
 */

import { useState } from 'react'
import {
  Alert,
  Button,
  cardClass,
  ConfirmDialog,
  useLocale,
  type FlashMessage,
} from '@ellr/ui'

type QuickBooksConnectionStatus = {
  connected: boolean
  realm_id?: string
  refresh_token_expired?: boolean
  needs_refresh?: boolean
}

type QuickBooksConnectionPanelProps = {
  bootstrapError: string | null
  message: FlashMessage | null
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
  bootstrapError,
  message,
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
      <h2 className="text-xl font-medium text-slate-900">{t('admin.quickbooksTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('admin.quickbooksConnectionHelp')}</p>

      {message && (
        <div className="mt-4">
          <Alert variant={message.type}>{message.text}</Alert>
        </div>
      )}

      {bootstrapError && (
        <div className="mt-4">
          <Alert variant="error">{bootstrapError}</Alert>
        </div>
      )}

      {statusLabel && (
        <p className="mt-4 text-sm text-slate-600">
          {t('admin.statusLabel')}{' '}
          <span className="font-medium text-slate-900">{statusLabel}</span>
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
