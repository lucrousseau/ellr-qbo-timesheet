/**
 * @file Factory for Stryker mutation testing configuration.
 */

/**
 * Default Stryker mutation-testing config for frontend workspaces.
 * @returns Stryker options (Vitest runner, thresholds, mutate globs).
 */
export function createStrykerConfig() {
  return {
    packageManager: 'npm',
    testRunner: 'vitest',
    coverageAnalysis: 'off',
    reporters: ['html', 'clear-text', 'json'],
    htmlReporter: {
      fileName: 'build/mutation/index.html',
    },
    jsonReporter: {
      fileName: 'build/mutation/report.json',
    },
    mutate: ['src/App.tsx', '!src/**/*.test.ts', '!src/**/*.test.tsx'],
    thresholds: {
      high: 80,
      low: 65,
      break: 55,
    },
    vitest: {
      configFile: 'vitest.config.ts',
      related: false,
    },
  }
}

export default createStrykerConfig()
