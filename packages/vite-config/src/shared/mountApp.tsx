import { StrictMode, type ComponentType } from 'react'
import { createRoot } from 'react-dom/client'

/**
 * Mounts a React app into `#root` under `StrictMode`.
 * @param App Root component for the workspace.
 */
export function mountApp(App: ComponentType) {
  const root = document.getElementById('root')

  if (!root) {
    throw new Error('Root element #root not found')
  }

  createRoot(root).render(
    <StrictMode>
      <App />
    </StrictMode>,
  )
}
