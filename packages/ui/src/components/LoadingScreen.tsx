/**
 * @file Full-page loading placeholder while auth state resolves.
 */

import { pageMainClass } from '../styles/tokens'

/**
 * Full-page screen shown while the session is bootstrapping.
 * @returns Minimal loading indicator.
 */
export function LoadingScreen() {
  return (
    <main className={pageMainClass}>
      <p className="text-slate-600">Loading...</p>
    </main>
  )
}
