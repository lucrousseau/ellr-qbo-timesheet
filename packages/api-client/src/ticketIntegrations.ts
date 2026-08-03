/**
 * @file Ticket integration API helpers for Jira / Linear scaffold.
 */

import { apiFetch } from './api'

/** Supported external ticket providers. */
export type TicketSource = 'manual' | 'jira' | 'linear'

/**
 * Status payload for ticket provider availability.
 */
export type TicketIntegrationStatus = {
  jira: { enabled: boolean; connected: boolean }
  linear: { enabled: boolean; connected: boolean }
  picker_available: boolean
}

/**
 * Ticket search hit returned by the integrations API.
 */
export type TicketSearchResult = {
  key: string
  title: string
  url: string | null
  source: Exclude<TicketSource, 'manual'>
  customer_ref: string | null
  project_ref: string | null
}

/**
 * Loads ticket integration status for the signed-in organization.
 * @returns Provider enablement and picker availability.
 */
export async function fetchTicketIntegrationStatus(): Promise<TicketIntegrationStatus> {
  const response = await apiFetch<{ data: TicketIntegrationStatus }>('/integrations/tickets/status')

  return response.data
}

/**
 * Searches connected ticket providers for the timer picker.
 * @param query Free-text ticket search.
 * @param options Optional provider filter and abort signal.
 * @returns Matching ticket rows.
 */
export async function searchTickets(
  query: string,
  options?: { provider?: Exclude<TicketSource, 'manual'>; signal?: AbortSignal },
): Promise<TicketSearchResult[]> {
  const search = new URLSearchParams({ q: query })

  if (options?.provider) {
    search.set('provider', options.provider)
  }

  const response = await apiFetch<{ data: TicketSearchResult[] }>(
    `/integrations/tickets?${search.toString()}`,
    { signal: options?.signal },
  )

  return response.data
}
