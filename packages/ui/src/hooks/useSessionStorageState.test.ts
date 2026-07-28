/**
 * @file Tests for sessionStorage-backed React state.
 */

import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it } from 'vitest'
import { useSessionStorageState } from './useSessionStorageState'

type SampleTab = 'preferences' | 'administrator'

/**
 * Type guard used in hook tests for valid sample tab ids.
 * @param value Raw sessionStorage value.
 * @returns Whether the value is a known sample tab id.
 */
function isSampleTab(value: string): value is SampleTab {
  return value === 'preferences' || value === 'administrator'
}

describe('useSessionStorageState', () => {
  const key = 'ellr.test.sessionValue'

  beforeEach(() => {
    sessionStorage.clear()
  })

  it('initializes from default when storage is empty', () => {
    const { result } = renderHook(() => useSessionStorageState(key, 'preferences'))

    expect(result.current[0]).toBe('preferences')
  })

  it('reads initial value from sessionStorage', () => {
    sessionStorage.setItem(key, 'administrator')

    const { result } = renderHook(() =>
      useSessionStorageState<SampleTab>(key, 'preferences', { isValid: isSampleTab }),
    )

    expect(result.current[0]).toBe('administrator')
  })

  it('rejects invalid stored values when isValid is provided', () => {
    sessionStorage.setItem(key, 'invalid')

    const { result } = renderHook(() =>
      useSessionStorageState<SampleTab>(key, 'preferences', { isValid: isSampleTab }),
    )

    expect(result.current[0]).toBe('preferences')
    expect(sessionStorage.getItem(key)).toBe('preferences')
  })

  it('persists updates to sessionStorage', () => {
    const { result } = renderHook(() =>
      useSessionStorageState<SampleTab>(key, 'preferences', { isValid: isSampleTab }),
    )

    act(() => {
      result.current[1]('administrator')
    })

    expect(result.current[0]).toBe('administrator')
    expect(sessionStorage.getItem(key)).toBe('administrator')
  })

  it('re-reads storage when the key changes', () => {
    sessionStorage.setItem('ellr.test.user-1', 'preferences')
    sessionStorage.setItem('ellr.test.user-2', 'administrator')

    const { result, rerender } = renderHook(
      ({ storageKey }) =>
        useSessionStorageState<SampleTab>(storageKey, 'preferences', { isValid: isSampleTab }),
      { initialProps: { storageKey: 'ellr.test.user-1' } },
    )

    expect(result.current[0]).toBe('preferences')

    rerender({ storageKey: 'ellr.test.user-2' })

    expect(result.current[0]).toBe('administrator')
  })
})
