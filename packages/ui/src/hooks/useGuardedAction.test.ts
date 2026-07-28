/**
 * @file Tests for guarded action invocation.
 */

import { act, renderHook } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { useGuardedAction } from './useGuardedAction'

describe('useGuardedAction', () => {
  it('ignores duplicate invocations while the action is pending', async () => {
    let resolveAction: () => void = () => {}
    const action = vi.fn(
      () =>
        new Promise<void>((resolve) => {
          resolveAction = resolve
        }),
    )

    const { result } = renderHook(() => useGuardedAction(action))

    let firstRun: Promise<void | undefined>
    let secondRun: Promise<void | undefined>

    await act(async () => {
      firstRun = result.current.run()
      secondRun = result.current.run()
    })

    expect(action).toHaveBeenCalledTimes(1)
    expect(result.current.pending).toBe(true)

    await act(async () => {
      resolveAction()
      await firstRun
      await secondRun
    })

    expect(result.current.pending).toBe(false)
  })

  it('allows a new invocation after the previous action settles', async () => {
    const action = vi.fn().mockResolvedValue(undefined)
    const { result } = renderHook(() => useGuardedAction(action))

    await act(async () => {
      await result.current.run()
      await result.current.run()
    })

    expect(action).toHaveBeenCalledTimes(2)
  })

  it('guards synchronous handlers', () => {
    const action = vi.fn()
    const { result } = renderHook(() => useGuardedAction(action))

    act(() => {
      void result.current.run()
      void result.current.run()
    })

    expect(action).toHaveBeenCalledTimes(1)
  })

  it('keeps pending true after a successful run when configured', async () => {
    const action = vi.fn().mockResolvedValue(undefined)
    const { result } = renderHook(() => useGuardedAction(action, { keepPendingOnSuccess: true }))

    await act(async () => {
      await result.current.run()
    })

    expect(action).toHaveBeenCalledTimes(1)
    expect(result.current.pending).toBe(true)
  })

  it('releases pending after a failed run when keepPendingOnSuccess is enabled', async () => {
    const action = vi.fn().mockRejectedValue(new Error('failed'))
    const { result } = renderHook(() => useGuardedAction(action, { keepPendingOnSuccess: true }))

    await act(async () => {
      await expect(result.current.run()).rejects.toThrow('failed')
    })

    expect(result.current.pending).toBe(false)
  })
})
