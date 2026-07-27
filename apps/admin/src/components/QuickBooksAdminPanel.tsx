/**
 * @file QuickBooks connection status, employee mapping, and OAuth actions.
 */

import { Alert, cardClass, inputClass, primaryButtonClass, secondaryButtonClass } from '@ellr/ui'

type QuickBooksConnectionStatus = {
  connected: boolean
  realm_id?: string
}

type QuickBooksAdminPanelProps = {
  userEmail: string
  bootstrapError: string | null
  message: { text: string; type: 'success' | 'error' | 'warning' } | null
  status: QuickBooksConnectionStatus | null
  connecting: boolean
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
  userEmail,
  bootstrapError,
  message,
  status,
  connecting,
  qboEmployeeRef,
  qboEmployeeName,
  savingEmployee,
  onQboEmployeeRefChange,
  onQboEmployeeNameChange,
  onSaveEmployee,
  onConnect,
  onDisconnect,
}: QuickBooksAdminPanelProps) {
  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">QuickBooks Online connection</h2>
      <p className="mt-2 text-sm text-slate-600">Signed in as {userEmail}</p>

      <form className="mt-6 space-y-4 rounded-lg border border-slate-200 p-4" onSubmit={onSaveEmployee}>
        <h3 className="text-sm font-medium text-slate-900">QuickBooks employee</h3>
        <p className="text-sm text-slate-600">
          Link this account to a QBO employee for the timesheet app.
        </p>
        <label className="block text-sm font-medium text-slate-700">
          QBO employee ID
          <input
            required
            className={inputClass}
            value={qboEmployeeRef}
            onChange={(event) => onQboEmployeeRefChange(event.target.value)}
          />
        </label>
        <label className="block text-sm font-medium text-slate-700">
          Employee name (optional)
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
          {savingEmployee ? 'Saving...' : 'Save employee'}
        </button>
      </form>

      {message && (
        <div className="mt-4">
          <Alert variant={message.type === 'error' ? 'error' : 'success'}>{message.text}</Alert>
        </div>
      )}

      {bootstrapError && (
        <div className="mt-4">
          <Alert variant="error">{bootstrapError}</Alert>
        </div>
      )}

      {status && (
        <p className="mt-4 text-sm text-slate-600">
          Status:{' '}
          <span className="font-medium text-slate-900">
            {status.connected ? `Connected (realm ${status.realm_id})` : 'Not connected'}
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
          {connecting ? 'Redirecting...' : 'Connect QuickBooks'}
        </button>
        {status?.connected && (
          <button type="button" onClick={onDisconnect} className={`${secondaryButtonClass} px-4 py-2.5`}>
            Disconnect QuickBooks
          </button>
        )}
      </div>
    </section>
  )
}
