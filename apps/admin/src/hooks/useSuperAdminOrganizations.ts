/**
 * @file Platform super-administrator organization management state.
 */

import { useCallback, useEffect, useState } from 'react'
import {
  createSuperAdminOrganization,
  deleteSuperAdminOrganization,
  fetchSuperAdminOrganizations,
  updateSuperAdminOrganization,
  ApiError,
  type SuperAdminOrganization,
} from '@ellr/api-client'
import {
  getApiErrorMessage,
  translateApiPasswordValidationMessage,
  translatePasswordPolicyErrors,
  useGuardedAction,
  useLocale,
} from '@ellr/ui'
import {
  loadPasswordPolicy,
  validatePasswordWithConfirmation,
} from '@ellr/password-policy'

type UseSuperAdminOrganizationsOptions = {
  enabled: boolean
  onError: (message: string) => void
  onSuccess: (message: string) => void
}

/**
 * Loads and mutates tenant organizations for platform super administrators.
 * @param options Feature gate and alert callbacks.
 * @returns Organization list state and CRUD handlers.
 */
export function useSuperAdminOrganizations({
  enabled,
  onError,
  onSuccess,
}: UseSuperAdminOrganizationsOptions) {
  const { locale, t } = useLocale()
  const [organizations, setOrganizations] = useState<SuperAdminOrganization[]>([])
  const [loading, setLoading] = useState(false)
  const [organizationName, setOrganizationName] = useState('')
  const [adminName, setAdminName] = useState('')
  const [adminEmail, setAdminEmail] = useState('')
  const [adminPassword, setAdminPassword] = useState('')
  const [adminPasswordConfirmation, setAdminPasswordConfirmation] = useState('')
  const [editingOrganizationId, setEditingOrganizationId] = useState<number | null>(null)
  const [editingName, setEditingName] = useState('')
  const [deletingOrganization, setDeletingOrganization] = useState<SuperAdminOrganization | null>(null)
  const [deleting, setDeleting] = useState(false)

  const loadOrganizations = useCallback(async () => {
    if (!enabled) {
      setOrganizations([])
      return
    }

    setLoading(true)

    try {
      setOrganizations(await fetchSuperAdminOrganizations())
    } catch (error) {
      onError(getApiErrorMessage(error, t('admin.loadClientsFailed'), locale))
    } finally {
      setLoading(false)
    }
  }, [enabled, locale, onError, t])

  useEffect(() => {
    void loadOrganizations()
  }, [loadOrganizations])

  const { pending: creating, run: runCreate } = useGuardedAction(async () => {
    const policy = loadPasswordPolicy()
    const validationErrors = validatePasswordWithConfirmation(
      adminPassword,
      adminPasswordConfirmation,
      policy,
    )

    if (validationErrors.length > 0) {
      onError(translatePasswordPolicyErrors(locale, validationErrors))
      return
    }

    try {
      const created = await createSuperAdminOrganization({
        organization_name: organizationName,
        name: adminName,
        email: adminEmail,
        password: adminPassword,
        password_confirmation: adminPasswordConfirmation,
      })

      setOrganizations((current) => [...current, created].sort((a, b) => a.name.localeCompare(b.name)))
      setOrganizationName('')
      setAdminName('')
      setAdminEmail('')
      setAdminPassword('')
      setAdminPasswordConfirmation('')
      onSuccess(t('admin.clientOrganizationCreated'))
    } catch (error) {
      const apiMessage = error instanceof ApiError ? error.message : ''
      const policyMessage = translateApiPasswordValidationMessage(locale, apiMessage)
      onError(
        policyMessage
          ?? getApiErrorMessage(error, t('admin.createClientOrganizationFailed'), locale),
      )
    }
  })

  const { pending: savingEdit, run: runSaveEdit } = useGuardedAction(async () => {
    if (editingOrganizationId === null) {
      return
    }

    try {
      const updated = await updateSuperAdminOrganization(editingOrganizationId, {
        name: editingName,
      })

      setOrganizations((current) =>
        current
          .map((organization) => (organization.id === updated.id ? updated : organization))
          .sort((a, b) => a.name.localeCompare(b.name)),
      )
      setEditingOrganizationId(null)
      setEditingName('')
      onSuccess(t('admin.clientOrganizationUpdated'))
    } catch (error) {
      onError(getApiErrorMessage(error, t('admin.updateClientOrganizationFailed'), locale))
    }
  })

  const startEditing = useCallback((organization: SuperAdminOrganization) => {
    setEditingOrganizationId(organization.id)
    setEditingName(organization.name)
  }, [])

  const cancelEditing = useCallback(() => {
    setEditingOrganizationId(null)
    setEditingName('')
  }, [])

  const confirmDelete = useCallback(async () => {
    if (deletingOrganization === null) {
      return false
    }

    setDeleting(true)

    try {
      await deleteSuperAdminOrganization(deletingOrganization.id)
      setOrganizations((current) =>
        current.filter((organization) => organization.id !== deletingOrganization.id),
      )
      onSuccess(t('admin.clientOrganizationDeleted'))
      return true
    } catch (error) {
      onError(getApiErrorMessage(error, t('admin.deleteClientOrganizationFailed'), locale))
      return false
    } finally {
      setDeleting(false)
    }
  }, [deletingOrganization, locale, onError, onSuccess, t])

  return {
    organizations,
    loading,
    organizationName,
    setOrganizationName,
    adminName,
    setAdminName,
    adminEmail,
    setAdminEmail,
    adminPassword,
    setAdminPassword,
    adminPasswordConfirmation,
    setAdminPasswordConfirmation,
    creating,
    onCreate: runCreate,
    editingOrganizationId,
    editingName,
    setEditingName,
    savingEdit,
    onStartEditing: startEditing,
    onCancelEditing: cancelEditing,
    onSaveEdit: runSaveEdit,
    deletingOrganization,
    setDeletingOrganization,
    deleting,
    onConfirmDelete: confirmDelete,
    reload: loadOrganizations,
  }
}
