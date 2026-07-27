import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5174,
    strictPort: true,
  },
  envPrefix: 'VITE_',
  resolve: {
    alias: {
      '@ellr/api-client': new URL('../../packages/api-client/src/index.ts', import.meta.url).pathname,
    },
  },
})
