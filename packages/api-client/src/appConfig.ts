/**
 * @file Public application configuration from the health endpoint.
 */

import { apiFetch } from './api'

/**
 * Client-relevant flags exposed by the Laravel API.
 */
export type AppConfig = {
  require_email_verification: boolean
}

/**
 * Loads public app configuration (no authentication required).
 * @returns Feature flags for the frontend.
 */
export async function fetchAppConfig(): Promise<AppConfig> {
  const response = await apiFetch<{
    status: string
    service: string
    require_email_verification: boolean
  }>('/health')

  return {
    require_email_verification: response.require_email_verification,
  }
}
