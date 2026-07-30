/**
 * @file Tests for shared Vite app configuration (local vs Docker dev server).
 */

import { readFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { UserConfig } from 'vite'
import { createAppConfig } from './createAppConfig'

/**
 * Resolves the configured Rollup manual chunk for a module id.
 * @param config Vite config returned by `createAppConfig`.
 * @param id Module path under test.
 * @returns Chunk name when the bundler assigns one.
 */
function manualChunkFor(config: UserConfig, id: string): string | undefined {
  const output = config.build?.rollupOptions?.output

  if (!output || Array.isArray(output)) {
    return undefined
  }

  const manualChunks = output.manualChunks

  if (typeof manualChunks !== 'function') {
    return undefined
  }

  return manualChunks(id, { getModuleInfo: () => null }) ?? undefined
}

describe('createAppConfig', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('documents relative api urls in frontend env examples', () => {
    const monorepoRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '../../..')
    const adminExample = readFileSync(path.join(monorepoRoot, 'apps/admin/.env.example'), 'utf8')
    const timesheetExample = readFileSync(path.join(monorepoRoot, 'apps/timesheet/.env.example'), 'utf8')

    expect(adminExample).toContain('VITE_API_URL=/api')
    expect(timesheetExample).toContain('VITE_API_URL=/api')
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

  it('includes Tailwind source paths for shared UI and both apps', () => {
    const monorepoRoot = path.join(path.dirname(fileURLToPath(import.meta.url)), '../../..')
    const appCss = readFileSync(path.join(monorepoRoot, 'packages/vite-config/src/shared/app.css'), 'utf8')

    expect(appCss).toContain('@source "../../../ui/src"')
    expect(appCss).toContain('@source "../../../../apps/admin/src"')
    expect(appCss).toContain('@source "../../../../apps/timesheet/src"')
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

  it('splits shared UI and vendor dependencies into stable build chunks', () => {
    const config = createAppConfig({ port: 5173, importMetaUrl: import.meta.url })

    expect(manualChunkFor(config, '/repo/packages/ui/src/index.ts')).toBe('ellr-ui')
    expect(manualChunkFor(config, '/repo/apps/admin/src/App.tsx')).toBeUndefined()
    expect(manualChunkFor(config, '/repo/node_modules/@headlessui/react/dist/index.js')).toBe('headlessui')
    expect(manualChunkFor(config, '/repo/node_modules/react-imask/dist/index.js')).toBe('imask')
    expect(manualChunkFor(config, '/repo/node_modules/react/index.js')).toBe('react-vendor')
    expect(manualChunkFor(config, '/repo/node_modules/scheduler/index.js')).toBe('react-vendor')
    expect(manualChunkFor(config, '/repo/node_modules/lodash/lodash.js')).toBe('vendor')
  })
})
