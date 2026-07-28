/// <reference types="vitest/config" />

import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { createVitestConfig } from '@ellr/vite-config/vitest'

const packageDir = path.dirname(fileURLToPath(import.meta.url))

export default createVitestConfig({ packageDir })
