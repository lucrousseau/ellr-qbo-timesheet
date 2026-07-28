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
    expect(config.server?.proxy).toEqual({
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/sanctum': { target: 'http://127.0.0.1:8000', changeOrigin: true },
    })
  })

  it('enables Docker host binding and polling when env vars are set', () => {
    vi.stubEnv('VITE_DOCKER', 'true')
    vi.stubEnv('CHOKIDAR_USEPOLLING', 'true')
    vi.stubEnv('VITE_API_PROXY_TARGET', 'http://api:8000')

    const config = createAppConfig({ port: 5174, importMetaUrl: import.meta.url })

    expect(config.server?.host).toBe(true)
    expect(config.server?.watch).toEqual({ usePolling: true, interval: 1000 })
    expect(config.server?.proxy).toEqual({
      '/api': { target: 'http://api:8000', changeOrigin: true },
      '/sanctum': { target: 'http://api:8000', changeOrigin: true },
    })
  })
})
