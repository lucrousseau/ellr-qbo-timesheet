/**
 * @file Tests for admin tab session storage helpers.
 */

import { beforeEach, describe, expect, it } from 'vitest'
import {
  adminActiveTabStorageKey,
  clearAdminActiveTabStorage,
  isAdminTab,
  LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY,
  LEGACY_ADMIN_TAB_ID,
  parseStoredAdminTab,
  readAdminActiveTab,
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
    expect(isAdminTab('integrations')).toBe(true)
    expect(isAdminTab(LEGACY_ADMIN_TAB_ID)).toBe(false)
    expect(isAdminTab('invalid')).toBe(false)
  })

  it('parses legacy administrator tab ids as integrations', () => {
    expect(parseStoredAdminTab(LEGACY_ADMIN_TAB_ID)).toBe('integrations')
    expect(parseStoredAdminTab('integrations')).toBe('integrations')
    expect(parseStoredAdminTab('preferences')).toBe('preferences')
    expect(parseStoredAdminTab('invalid')).toBeNull()
  })

  it('migrates legacy administrator values when reading storage', () => {
    const key = adminActiveTabStorageKey(7)
    sessionStorage.setItem(key, LEGACY_ADMIN_TAB_ID)

    expect(readAdminActiveTab(key)).toBe('integrations')
    expect(sessionStorage.getItem(key)).toBe('integrations')
  })

  it('clears scoped and legacy storage entries', () => {
    sessionStorage.setItem(adminActiveTabStorageKey(7), 'integrations')
    sessionStorage.setItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY, LEGACY_ADMIN_TAB_ID)

    clearAdminActiveTabStorage(7)

    expect(sessionStorage.getItem(adminActiveTabStorageKey(7))).toBeNull()
    expect(sessionStorage.getItem(LEGACY_ADMIN_ACTIVE_TAB_STORAGE_KEY)).toBeNull()
  })
})
