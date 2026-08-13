/**
 * @file Tests for sync group deep-link viewer state.
 */

import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useTimeEntrySyncGroupViewer } from './useTimeEntrySyncGroupViewer'

describe('useTimeEntrySyncGroupViewer', () => {
  it('opens from the sync_group query parameter', () => {
    window.history.replaceState({}, '', '/?sync_group=11111111-1111-4111-8111-111111111111')

    const { result } = renderHook(() => useTimeEntrySyncGroupViewer())

    expect(result.current.syncGroupPublicId).toBe('11111111-1111-4111-8111-111111111111')
  })

  it('clears the query parameter when closing', () => {
    window.history.replaceState({}, '', '/?sync_group=11111111-1111-4111-8111-111111111111')
    const { result } = renderHook(() => useTimeEntrySyncGroupViewer())

    act(() => {
      result.current.closeSyncGroup()
    })

    expect(result.current.syncGroupPublicId).toBeNull()
    expect(window.location.search).toBe('')
  })
})
