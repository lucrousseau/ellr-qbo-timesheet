/**
 * @file Tests for shared Vite app configuration (local vs Docker dev server).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { createAppConfig } from './createAppConfig'

describe('createAppConfig', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('keeps default Vite server options outside Docker', () => {
    const config = createAppConfig({ port: 5173, importMetaUrl: import.meta.url })

    expect(config.server?.port).toBe(5173)
    expect(config.server?.strictPort).toBe(true)
    expect(config.server?.host).toBeUndefined()
    expect(config.server?.watch).toBeUndefined()
  })

  it('enables Docker host binding and polling when env vars are set', () => {
    vi.stubEnv('VITE_DOCKER', 'true')
    vi.stubEnv('CHOKIDAR_USEPOLLING', 'true')

    const config = createAppConfig({ port: 5174, importMetaUrl: import.meta.url })

    expect(config.server?.host).toBe(true)
    expect(config.server?.watch).toEqual({ usePolling: true, interval: 1000 })
  })
})
