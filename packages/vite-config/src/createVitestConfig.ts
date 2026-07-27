import fs from 'node:fs'
import path from 'node:path'
import type { UserConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

function findMonorepoRoot(start: string): string {
  let dir = start

  for (;;) {
    const packageJsonPath = path.join(dir, 'package.json')
    if (fs.existsSync(packageJsonPath)) {
      const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8')) as { workspaces?: unknown }
      if (packageJson.workspaces) {
        return dir
      }
    }

    const parent = path.dirname(dir)
    if (parent === dir) {
      return start
    }

    dir = parent
  }
}

type CreateVitestConfigOptions = {
  packageDir: string
}

export function createVitestConfig({ packageDir }: CreateVitestConfigOptions): UserConfig {
  const monorepoRoot = findMonorepoRoot(packageDir)

  return defineConfig({
    plugins: [react()],
    resolve: {
      alias: {
        '@ellr/api-client': path.join(monorepoRoot, 'packages/api-client/src/index.ts'),
        '@ellr/ui': path.join(monorepoRoot, 'packages/ui/src/index.ts'),
        '@ellr/test-utils': path.join(monorepoRoot, 'packages/test-utils/src/index.ts'),
      },
    },
    test: {
      include: ['src/**/*.{test,spec}.{ts,tsx}'],
      environment: 'jsdom',
      setupFiles: [path.join(monorepoRoot, 'packages/vite-config/src/shared/test/setup.ts')],
      coverage: {
        provider: 'v8',
        reporter: ['text', 'lcov', 'html'],
        include: ['src/**/*.{ts,tsx}'],
        exclude: ['src/test/**', 'src/main.tsx'],
        thresholds: {
          lines: 85,
          functions: 85,
          branches: 75,
          statements: 85,
        },
      },
    },
  })
}
