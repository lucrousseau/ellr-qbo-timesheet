import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

/// <reference types="@stryker-mutator/vitest-runner" />

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

const configDir = path.dirname(fileURLToPath(import.meta.url))
const monorepoRoot = findMonorepoRoot(configDir)

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@ellr/api-client': path.join(monorepoRoot, 'packages/api-client/src/index.ts'),
    },
  },
  test: {
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
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
