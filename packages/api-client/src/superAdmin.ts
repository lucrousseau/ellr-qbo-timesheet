/**
 * @file Platform super-administrator endpoints for tenant organization management.
 */

import { apiFetch } from './api'
import type { Organization } from './auth'

/**
 * Tenant organization row for platform operator dashboards.
 */
export type SuperAdminOrganization = Organization & {
  users_count: number
  created_at: string | null
}

/**
 * Payload for creating a tenant organization and founding administrator.
 */
export type CreateSuperAdminOrganizationPayload = {
  organization_name: string
  first_name: string
  last_name: string
  email: string
  password: string
  password_confirmation: string
}

/**
 * Payload for updating a tenant organization.
 */
export type UpdateSuperAdminOrganizationPayload = {
  name: string
}

/**
 * Lists all tenant organizations for platform super administrators.
 * @returns Organization rows with aggregate user counts.
 */
export async function fetchSuperAdminOrganizations(): Promise<SuperAdminOrganization[]> {
  const response = await apiFetch<{ data: SuperAdminOrganization[] }>('/super-admin/organizations')

  return response.data
}

/**
 * Creates a tenant organization and its founding administrator account.
 * @param payload Organization and administrator details.
 * @returns Created organization row.
 */
export async function createSuperAdminOrganization(
  payload: CreateSuperAdminOrganizationPayload,
): Promise<SuperAdminOrganization> {
  const response = await apiFetch<{ data: SuperAdminOrganization }>('/super-admin/organizations', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.data
}

/**
 * Updates mutable tenant organization attributes.
 * @param organizationId Tenant organization id.
 * @param payload Fields to update.
 * @returns Updated organization row.
 */
export async function updateSuperAdminOrganization(
  organizationId: number,
  payload: UpdateSuperAdminOrganizationPayload,
): Promise<SuperAdminOrganization> {
  const response = await apiFetch<{ data: SuperAdminOrganization }>(
    `/super-admin/organizations/${organizationId}`,
    {
      method: 'PATCH',
      body: JSON.stringify(payload),
    },
  )

  return response.data
}

/**
 * Deletes a tenant organization and purges linked QuickBooks realm data.
 * @param organizationId Tenant organization id to delete.
 */
export async function deleteSuperAdminOrganization(organizationId: number): Promise<void> {
  await apiFetch(`/super-admin/organizations/${organizationId}`, {
    method: 'DELETE',
  })
}
