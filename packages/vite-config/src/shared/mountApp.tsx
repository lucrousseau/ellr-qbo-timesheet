import { StrictMode, type ComponentType } from 'react'
import { createRoot } from 'react-dom/client'

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
