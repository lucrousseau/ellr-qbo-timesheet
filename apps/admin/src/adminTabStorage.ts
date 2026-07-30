/**
 * @file Session storage helpers for the admin dashboard active tab.
 */

import { useCallback, useEffect, useState } from 'react'

export const ADMIN_ACTIVE_TAB_STORAGE_PREFIX = 'ellr.admin.activeTab'

/** Legacy storage key before per-user scoping. */
export const LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY = ADMIN_ACTIVE_TAB_STORAGE_PREFIX

/** Legacy tab id before the integrations rename. */
export const LEGACY_ADMIN_TAB_ID = 'administrator'

/** Persisted admin dashboard tab ids. */
export type AdminTab = 'preferences' | 'integrations' | 'clients'

/** Known admin dashboard tab ids. */
const ADMIN_TABS: readonly AdminTab[] = ['preferences', 'integrations', 'clients']

/**
 * Builds a per-user sessionStorage key for the active admin tab.
 * @param userId Authenticated user id.
 * @returns Namespaced storage key.
 */
export function adminActiveTabStorageKey(userId: number): string {
  return `${ADMIN_ACTIVE_TAB_STORAGE_PREFIX}:${userId}`
}

/**
 * Type guard for persisted admin tab ids.
 * @param value Raw sessionStorage value.
 * @returns Whether the value is a known admin tab id.
 */
export function isAdminTab(value: string): value is AdminTab {
  return (ADMIN_TABS as readonly string[]).includes(value)
}

/**
 * Parses a stored tab id, migrating legacy administrator values.
 * @param value Raw sessionStorage value.
 * @returns Normalized tab id or null when unknown.
 */
export function parseStoredAdminTab(value: string): AdminTab | null {
  if (value === LEGACY_ADMIN_TAB_ID) {
    return 'integrations'
  }

  if (isAdminTab(value)) {
    return value
  }

  return null
}

/**
 * Reads the active admin tab from sessionStorage with legacy migration.
 * @param storageKey Per-user sessionStorage key.
 * @param defaultValue Fallback when storage is empty or invalid.
 * @returns Normalized active tab id.
 */
export function readAdminActiveTab(
  storageKey: string,
  defaultValue: AdminTab = 'preferences',
): AdminTab {
  if (typeof sessionStorage === 'undefined') {
    return defaultValue
  }

  try {
    const stored = sessionStorage.getItem(storageKey)
    if (stored === null) {
      return defaultValue
    }

    const parsed = parseStoredAdminTab(stored)
    if (parsed === null) {
      sessionStorage.setItem(storageKey, defaultValue)

      return defaultValue
    }

    if (stored !== parsed) {
      sessionStorage.setItem(storageKey, parsed)
    }

    return parsed
  } catch {
    return defaultValue
  }
}

/**
 * Persists and exposes the active admin tab for a signed-in user.
 * @param userId Authenticated user id.
 * @returns Active tab state and setter.
 */
export function useAdminActiveTab(userId: number): [AdminTab, (value: AdminTab) => void] {
  const key = adminActiveTabStorageKey(userId)
  const [activeTab, setActiveTabState] = useState<AdminTab>(() => readAdminActiveTab(key))

  useEffect(() => {
    setActiveTabState(readAdminActiveTab(key))
  }, [key])

  const setActiveTab = useCallback(
    (value: AdminTab) => {
      setActiveTabState(value)

      try {
        sessionStorage.setItem(key, value)
      } catch {
        // Ignore privacy-mode or quota failures.
      }
    },
    [key],
  )

  return [activeTab, setActiveTab]
}

/**
 * Removes persisted tab state for a user and any legacy unscoped entry.
 * @param userId Authenticated user id.
 */
export function clearAdminActiveTabStorage(userId: number): void {
  if (typeof sessionStorage === 'undefined') {
    return
  }

  try {
    sessionStorage.removeItem(adminActiveTabStorageKey(userId))
    sessionStorage.removeItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY)
  } catch {
    // Ignore privacy-mode or quota failures.
  }
}
