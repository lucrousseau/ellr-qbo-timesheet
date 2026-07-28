/**
 * @file Tests for admin tab session storage helpers.
 */

import { beforeEach, describe, expect, it } from 'vitest'
import {
  adminActiveTabStorageKey,
  clearAdminActiveTabStorage,
  isAdminTab,
  LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY,
} from './adminTabStorage'

describe('adminTabStorage', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('builds a per-user storage key', () => {
    expect(adminActiveTabStorageKey(42)).toBe('ellr.admin.activeTab:42')
  })

  it('validates known admin tab ids', () => {
    expect(isAdminTab('preferences')).toBe(true)
    expect(isAdminTab('administrator')).toBe(true)
    expect(isAdminTab('invalid')).toBe(false)
  })

  it('clears scoped and legacy storage entries', () => {
    sessionStorage.setItem(adminActiveTabStorageKey(7), 'administrator')
    sessionStorage.setItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY, 'administrator')

    clearAdminActiveTabStorage(7)

    expect(sessionStorage.getItem(adminActiveTabStorageKey(7))).toBeNull()
    expect(sessionStorage.getItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY)).toBeNull()
  })
})
