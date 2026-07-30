/**
 * @file Public application configuration from the health endpoint.
 */

import { apiFetch } from './api'

/** Default timer cap when health config is unavailable (24 hours). */
export const DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS = 86_400

/**
 * Client-relevant flags exposed by the Laravel API.
 */
export type AppConfig = {
  require_email_verification: boolean
  time_tracker_max_accumulated_seconds: number
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
    time_tracker_max_accumulated_seconds?: number
  }>('/health')

  const maxAccumulated = response.time_tracker_max_accumulated_seconds

  return {
    require_email_verification: response.require_email_verification,
    time_tracker_max_accumulated_seconds:
      typeof maxAccumulated === 'number' && maxAccumulated > 0
        ? maxAccumulated
        : DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS,
  }
}
