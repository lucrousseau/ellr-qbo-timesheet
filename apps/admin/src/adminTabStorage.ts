/**
 * @file Session storage helpers for the admin dashboard active tab.
 */

export const ADMIN_ACTIVE_TAB_STORAGE_PREFIX = 'ellr.admin.activeTab'

/** Legacy storage key before per-user scoping. */
export const LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY = ADMIN_ACTIVE_TAB_STORAGE_PREFIX

/** Persisted admin dashboard tab ids. */
export type AdminTab = 'preferences' | 'administrator'

/** Known admin dashboard tab ids. */
const ADMIN_TABS: readonly AdminTab[] = ['preferences', 'administrator']

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
