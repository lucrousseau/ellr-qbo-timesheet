import path from 'node:path'
import { fileURLToPath } from 'node:url'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, type UserConfig } from 'vite'

type CreateAppConfigOptions = {
  port: number
  importMetaUrl: string
}

export function createAppConfig({ port, importMetaUrl }: CreateAppConfigOptions): UserConfig {
  const configDir = path.dirname(fileURLToPath(importMetaUrl))
  const monorepoRoot = path.join(configDir, '../..')

  return defineConfig({
    plugins: [react(), tailwindcss()],
    server: {
      port,
      strictPort: true,
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
