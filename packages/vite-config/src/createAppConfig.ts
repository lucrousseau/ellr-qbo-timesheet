/**
 * @file Factory for Vite app configuration shared by admin and timesheet.
 */

import path from 'node:path'
import { fileURLToPath } from 'node:url'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, type UserConfig } from 'vite'

type CreateAppConfigOptions = {
  port: number
  importMetaUrl: string
}

/**
 * Shared Vite config for React apps (aliases, Tailwind, dev server port).
 * @param options Dev server port and calling module `import.meta.url`.
 * @returns Vite `UserConfig` for `defineConfig`.
 */
export function createAppConfig({ port, importMetaUrl }: CreateAppConfigOptions): UserConfig {
  const configDir = path.dirname(fileURLToPath(importMetaUrl))
  const monorepoRoot = path.join(configDir, '../..')
  const isDocker = process.env.VITE_DOCKER === 'true'
  const usePolling = process.env.CHOKIDAR_USEPOLLING === 'true'

  return defineConfig({
    plugins: [react(), tailwindcss()],
    server: {
      port,
      strictPort: true,
      ...(isDocker ? { host: true } : {}),
      ...(usePolling ? { watch: { usePolling: true, interval: 1000 } } : {}),
    },
    envPrefix: 'VITE_',
    resolve: {
      alias: {
        '@ellr/api-client': path.join(monorepoRoot, 'packages/api-client/src/index.ts'),
        '@ellr/ui': path.join(monorepoRoot, 'packages/ui/src/index.ts'),
      },
    },
  })
}
