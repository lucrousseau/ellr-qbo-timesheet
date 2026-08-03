/**
 * @file Loads ticket integration status for the admin Integrations tab.
 */

import { useEffect, useState } from 'react'
import {
  fetchAppConfig,
  fetchTicketIntegrationStatus,
  type TicketIntegrationStatus,
} from '@ellr/api-client'

type TicketIntegrationsState = {
  status: TicketIntegrationStatus | null
  configEnabled: { jira: boolean; linear: boolean }
}

const emptyState = (): TicketIntegrationsState => ({
  status: null,
  configEnabled: { jira: false, linear: false },
})

/**
 * Fetches public config and organization ticket integration status.
 * @returns Status payload and config-enabled flags for Jira / Linear.
 */
export function useTicketIntegrations(): TicketIntegrationsState {
  const [state, setState] = useState<TicketIntegrationsState>(emptyState)

  useEffect(() => {
    let cancelled = false

    void Promise.all([fetchAppConfig(), fetchTicketIntegrationStatus()])
      .then(([config, integrationStatus]) => {
        if (cancelled) {
          return
        }

        setState({
          status: integrationStatus,
          configEnabled: {
            jira: config.ticket_integrations.jira_enabled,
            linear: config.ticket_integrations.linear_enabled,
          },
        })
      })
      .catch(() => {
        if (!cancelled) {
          setState(emptyState())
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  return state
}
