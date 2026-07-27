import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { createVitestConfig } from '@ellr/vite-config/vitest'

export default createVitestConfig({
  packageDir: path.dirname(fileURLToPath(import.meta.url)),
})
