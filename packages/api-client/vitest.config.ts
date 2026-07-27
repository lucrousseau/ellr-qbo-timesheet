/// <reference types="@stryker-mutator/vitest-runner" />

import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'jsdom',
    coverage: {
      provider: 'v8',
      thresholds: {
        lines: 85,
        functions: 85,
        branches: 75,
        statements: 85,
      },
    },
  },
})
