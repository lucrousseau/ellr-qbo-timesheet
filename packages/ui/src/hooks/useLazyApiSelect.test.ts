/**
 * @file Tests for lazy API-backed select loading.
 */

import { act, renderHook } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { useLazyApiSelect } from './useLazyApiSelect'

describe('useLazyApiSelect', () => {
  const fetch = vi.fn()
  const onError = vi.fn()

  beforeEach(() => {
    fetch.mockReset()
    onError.mockReset()
    fetch.mockResolvedValue([{ id: '1' }])
  })

  it('does not fetch until the select is opened', async () => {
    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    expect(fetch).not.toHaveBeenCalled()
    expect(result.current.items).toEqual([])
    expect(result.current.loaded).toBe(false)
  })

  it('loads options from cache on each open interaction', async () => {
    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      await result.current.onOpen()
    })

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch).toHaveBeenCalledWith(false, expect.any(AbortSignal))
    expect(result.current.items).toEqual([{ id: '1' }])
    expect(result.current.loaded).toBe(true)

    await act(async () => {
      await result.current.onOpen()
    })

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(fetch).toHaveBeenNthCalledWith(2, false, expect.any(AbortSignal))
  })

  it('aborts the previous fetch when open is triggered again', async () => {
    let rejectFirst: (() => void) | undefined
    fetch
      .mockImplementationOnce((_refresh: boolean, signal: AbortSignal) => {
        return new Promise((_resolve, reject) => {
          rejectFirst = () => reject(new DOMException('Aborted', 'AbortError'))
          signal.addEventListener('abort', rejectFirst)
        })
      })
      .mockResolvedValueOnce([{ id: '2' }])

    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      void result.current.onOpen()
      await result.current.onOpen()
    })

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(onError).not.toHaveBeenCalled()
    expect(result.current.items).toEqual([{ id: '2' }])
    expect(rejectFirst).toBeDefined()
  })

  it('clears cached options while a refresh is in flight', async () => {
    let resolveSecond: (value: { id: string }[]) => void = () => {}
    fetch
      .mockResolvedValueOnce([{ id: '1' }, { id: '2' }])
      .mockImplementationOnce(
        (_refresh: boolean, signal: AbortSignal) =>
          new Promise((resolve, reject) => {
            signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')))
            resolveSecond = resolve
          }),
      )

    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      await result.current.onOpen()
    })

    expect(result.current.items).toEqual([{ id: '1' }, { id: '2' }])

    await act(async () => {
      void result.current.onOpen()
    })

    expect(result.current.loading).toBe(true)
    expect(result.current.items).toEqual([])

    await act(async () => {
      resolveSecond([{ id: '3' }])
    })

    expect(result.current.loading).toBe(false)
    expect(result.current.items).toEqual([{ id: '3' }])
  })

  it('reports errors and clears options when fetch fails', async () => {
    fetch.mockRejectedValue(new Error('network'))

    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      await result.current.onOpen()
    })

    expect(onError).toHaveBeenCalledWith('Load failed')
    expect(result.current.items).toEqual([])
    expect(result.current.loaded).toBe(true)
  })

  it('aborts an in-flight fetch when the panel closes', async () => {
    let resolveFetch: (value: { id: string }[]) => void = () => {}
    fetch.mockImplementation(
      (_refresh: boolean, signal: AbortSignal) =>
        new Promise((resolve, reject) => {
          signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')))
          resolveFetch = resolve
        }),
    )

    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      void result.current.onOpen()
    })

    act(() => {
      result.current.onClose()
    })

    expect(result.current.loading).toBe(false)
    expect(result.current.loaded).toBe(false)
    expect(onError).not.toHaveBeenCalled()

    await act(async () => {
      resolveFetch([{ id: '1' }])
    })

    expect(result.current.items).toEqual([])
  })

  it('clears cached options when the panel closes', async () => {
    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      { wrapper: createLocaleWrapper() },
    )

    await act(async () => {
      await result.current.onOpen()
    })

    expect(result.current.items).toEqual([{ id: '1' }])
    expect(result.current.loaded).toBe(true)

    act(() => {
      result.current.onClose()
    })

    expect(result.current.items).toEqual([])
    expect(result.current.loaded).toBe(false)
  })

  it('clears cached options when disabled', async () => {
    const { result, rerender } = renderHook(
      ({ enabled }: { enabled: boolean }) =>
        useLazyApiSelect({
          enabled,
          fetch,
          onError,
          errorMessage: 'Load failed',
        }),
      {
        wrapper: createLocaleWrapper(),
        initialProps: { enabled: true },
      },
    )

    await act(async () => {
      await result.current.onOpen()
    })

    expect(result.current.items).toEqual([{ id: '1' }])

    rerender({ enabled: false })

    expect(result.current.items).toEqual([])
    expect(result.current.loaded).toBe(false)
  })

  it('seeds options from prefetched items when enabled', async () => {
    const seedItems = [{ id: 'seed' }]
    const { result } = renderHook(
      () =>
        useLazyApiSelect({
          enabled: true,
          fetch,
          onError,
          errorMessage: 'Load failed',
          seedItems,
        }),
      { wrapper: createLocaleWrapper() },
    )

    expect(fetch).not.toHaveBeenCalled()
    expect(result.current.items).toEqual([{ id: 'seed' }])
    expect(result.current.loaded).toBe(true)
  })
})
