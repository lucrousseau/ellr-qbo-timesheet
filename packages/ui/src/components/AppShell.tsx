import type { ReactNode } from 'react'
import { pageMainClass, pageTitleClass, secondaryButtonClass } from '../styles/tokens'

type AppShellProps = {
  title: string
  subtitle?: string
  userEmail?: string | null
  onLogout?: () => void
  children: ReactNode
}

export function AppShell({ title, subtitle, userEmail, onLogout, children }: AppShellProps) {
  return (
    <main className={pageMainClass}>
      <header className="mb-8 flex items-start justify-between gap-4">
        <div>
          <h1 className={pageTitleClass}>{title}</h1>
          {subtitle && <p className="mt-2 text-slate-600">{subtitle}</p>}
          {userEmail && !subtitle && (
            <p className="mt-2 text-slate-600">Connecté en tant que {userEmail}</p>
          )}
        </div>
        {userEmail && onLogout && (
          <button type="button" onClick={onLogout} className={secondaryButtonClass}>
            Déconnexion
          </button>
        )}
      </header>
      {children}
    </main>
  )
}
