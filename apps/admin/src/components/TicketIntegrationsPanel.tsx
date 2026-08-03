/**
 * @file Admin scaffold panel for Jira / Linear ticket integrations.
 */

import { useEffect, useState } from 'react'
import { fetchAppConfig, fetchTicketIntegrationStatus, type TicketIntegrationStatus } from '@ellr/api-client'
import { cardClass, useLocale } from '@ellr/ui'

type ProviderRow = {
  id: 'jira' | 'linear'
  labelKey: 'admin.ticketIntegrationsJira' | 'admin.ticketIntegrationsLinear'
  enabled: boolean
  connected: boolean
}

/**
 * Shows ticket tracker integration status under the admin Integrations tab.
 * @returns Ticket integrations card.
 */
export function TicketIntegrationsPanel() {
  const { t } = useLocale()
  const [status, setStatus] = useState<TicketIntegrationStatus | null>(null)
  const [configEnabled, setConfigEnabled] = useState({ jira: false, linear: false })

  useEffect(() => {
    let cancelled = false

    void Promise.all([fetchAppConfig(), fetchTicketIntegrationStatus()])
      .then(([config, integrationStatus]) => {
        if (cancelled) {
          return
        }

        setConfigEnabled({
          jira: config.ticket_integrations.jira_enabled,
          linear: config.ticket_integrations.linear_enabled,
        })
        setStatus(integrationStatus)
      })
      .catch(() => {
        if (!cancelled) {
          setStatus(null)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  const providers: ProviderRow[] = [
    {
      id: 'jira',
      labelKey: 'admin.ticketIntegrationsJira',
      enabled: status?.jira.enabled ?? configEnabled.jira,
      connected: status?.jira.connected ?? false,
    },
    {
      id: 'linear',
      labelKey: 'admin.ticketIntegrationsLinear',
      enabled: status?.linear.enabled ?? configEnabled.linear,
      connected: status?.linear.connected ?? false,
    },
  ]

  return (
    <section className={cardClass}>
      <h2 className="text-xl font-medium text-slate-900">{t('admin.ticketIntegrationsTitle')}</h2>
      <p className="mt-2 text-sm text-slate-600">{t('admin.ticketIntegrationsHelp')}</p>

      <ul className="mt-4 space-y-3">
        {providers.map((provider) => {
          let stateLabel = t('admin.ticketIntegrationsComingSoon')

          if (provider.connected) {
            stateLabel = t('admin.ticketIntegrationsConnected')
          } else if (provider.enabled) {
            stateLabel = t('admin.ticketIntegrationsEnabledPending')
          }

          return (
            <li
              key={provider.id}
              className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3 text-sm"
            >
              <span className="font-medium text-slate-900">{t(provider.labelKey)}</span>
              <span className="text-slate-600">{stateLabel}</span>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
