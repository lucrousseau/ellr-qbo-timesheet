/**
 * @file QuickBooks connection status, employee mapping, and OAuth actions.
 */

import { Alert, cardClass, inputClass, primaryButtonClass, secondaryButtonClass, useLocale, type FlashMessage } from '@ellr/ui'

type QuickBooksConnectionStatus = {
  connected: boolean
  realm_id?: string
}

type QuickBooksAdminPanelProps = {
  bootstrapError: string | null
  message: FlashMessage | null
  status: QuickBooksConnectionStatus | null
  connecting: boolean
  disconnecting: boolean
  qboEmployeeRef: string
  qboEmployeeName: string
  savingEmployee: boolean
  onQboEmployeeRefChange: (value: string) => void
  onQboEmployeeNameChange: (value: string) => void
  onSaveEmployee: (event: React.FormEvent) => void
  onConnect: () => void
  onDisconnect: () => void
}

/**
 * Admin dashboard section for QBO employee mapping and OAuth connection.
 * @param props Panel state and event handlers from `useQuickBooksAdmin`.
 * @returns QuickBooks admin card content.
 */
export function QuickBooksAdminPanel({
  bootstrapError,
  message,
  status,
  connecting,
  disconnecting,
  qboEmployeeRef,
  qboEmployeeName,
  savingEmployee,
  onQboEmployeeRefChange,
  onQboEmployeeNameChange,
  onSaveEmployee,
  onConnect,
  onDisconnect,
}: QuickBooksAdminPanelProps) {
  const { t } = useLocale()

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('admin.quickbooksTitle')}</h2>

      <form className="mt-6 space-y-4 rounded-lg border border-slate-200 p-4" onSubmit={onSaveEmployee}>
        <h3 className="text-sm font-medium text-slate-900">{t('admin.quickbooksEmployeeTitle')}</h3>
        <p className="text-sm text-slate-600">{t('admin.quickbooksEmployeeHelp')}</p>
        <label className="block text-sm font-medium text-slate-700">
          {t('admin.qboEmployeeId')}
          <input
            required
            className={inputClass}
            value={qboEmployeeRef}
            onChange={(event) => onQboEmployeeRefChange(event.target.value)}
          />
        </label>
        <label className="block text-sm font-medium text-slate-700">
          {t('admin.employeeNameOptional')}
          <input
            className={inputClass}
            value={qboEmployeeName}
            onChange={(event) => onQboEmployeeNameChange(event.target.value)}
          />
        </label>
        <button
          type="submit"
          disabled={savingEmployee}
          className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
        >
          {savingEmployee ? t('common.saving') : t('admin.saveEmployee')}
        </button>
      </form>

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

      {status && (
        <p className="mt-4 text-sm text-slate-600">
          {t('admin.statusLabel')}{' '}
          <span className="font-medium text-slate-900">
            {status.connected
              ? t('admin.statusConnected', { realmId: status.realm_id ?? '' })
              : t('admin.statusNotConnected')}
          </span>
        </p>
      )}

      <div className="mt-6 flex gap-3">
        <button
          type="button"
          onClick={onConnect}
          disabled={connecting}
          className={primaryButtonClass}
        >
          {connecting ? t('admin.redirecting') : t('admin.connectQuickBooks')}
        </button>
        {status?.connected && (
          <button
            type="button"
            onClick={onDisconnect}
            disabled={disconnecting}
            className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
          >
            {disconnecting ? t('admin.disconnecting') : t('admin.disconnectQuickBooks')}
          </button>
        )}
      </div>
    </section>
  )
}
