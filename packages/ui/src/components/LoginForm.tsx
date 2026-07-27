/**
 * @file Email and password sign-in form for Sanctum session auth.
 */

import type { FormEvent, ReactNode } from 'react'
import { cardClass, inputClass, pageMainClass, pageTitleClass, primaryButtonClass } from '../styles/tokens'
import { Alert } from './Alert'

type LoginFormProps = {
  title: string
  subtitle?: string
  email: string
  password: string
  onEmailChange: (value: string) => void
  onPasswordChange: (value: string) => void
  onSubmit: (event: FormEvent) => void
  error?: string | null
  heading?: string
  footer?: ReactNode
}

/**
 * Reusable Sanctum sign-in form (admin and timesheet).
 * @param props.title Page title.
 * @param props.subtitle Optional intro text.
 * @param props.email Controlled email field value.
 * @param props.password Controlled password field value.
 * @param props.onEmailChange Updates the email value.
 * @param props.onPasswordChange Updates the password value.
 * @param props.onSubmit Form submit handler (sign-in).
 * @param props.error Error message above the form.
 * @param props.heading Sign-in card heading.
 * @param props.footer Optional content below the button (e.g. flash alert).
 * @returns Complete sign-in page.
 */
export function LoginForm({
  title,
  subtitle,
  email,
  password,
  onEmailChange,
  onPasswordChange,
  onSubmit,
  error,
  heading = 'Sign in',
  footer,
}: LoginFormProps) {
  return (
    <main className={pageMainClass}>
      <header className="mb-8">
        <h1 className={pageTitleClass}>{title}</h1>
        {subtitle && <p className="mt-2 text-slate-600">{subtitle}</p>}
      </header>
      <section className={cardClass}>
        <h2 className="text-xl font-medium text-slate-900">{heading}</h2>

        {error && (
          <div className="mt-4">
            <Alert variant="error">{error}</Alert>
          </div>
        )}

        <form className="mt-4 space-y-4" onSubmit={onSubmit}>
          <label className="block text-sm font-medium text-slate-700">
            Email
            <input
              type="email"
              required
              className={inputClass}
              value={email}
              onChange={(event) => onEmailChange(event.target.value)}
            />
          </label>
          <label className="block text-sm font-medium text-slate-700">
            Password
            <input
              type="password"
              required
              className={inputClass}
              value={password}
              onChange={(event) => onPasswordChange(event.target.value)}
            />
          </label>
          <button type="submit" className={primaryButtonClass}>
            Sign in
          </button>
          {footer}
        </form>
      </section>
    </main>
  )
}
