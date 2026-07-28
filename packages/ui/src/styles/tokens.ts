/**
 * @file Shared Tailwind class tokens for cards, inputs, and buttons.
 */

export const inputClass =
  'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200'

export const pageMainClass = 'mx-auto max-w-3xl px-6 py-10'

export const pageTitleClass = 'text-3xl font-semibold tracking-tight text-slate-900'

export const cardClass = 'rounded-xl border border-slate-200 bg-white p-6 shadow-sm'

/**
 * Primary action button chrome. Prefer the `Button` component in apps.
 * @deprecated Use `Button` from `@ellr/ui` instead.
 */
export const primaryButtonClass =
  'rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50'

/**
 * Compact primary button for dense header chrome. Used via `Button` size `compact`.
 */
export const primaryButtonCompactClass =
  'rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50'

/**
 * Secondary action button chrome. Prefer the `Button` component in apps.
 * @deprecated Use `Button` from `@ellr/ui` instead.
 */
export const secondaryButtonClass =
  'rounded-lg border border-slate-300 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50'

/**
 * Compact secondary button for dense header chrome. Used via `Button` size `compact`.
 */
export const secondaryButtonCompactClass =
  'rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50'

/**
 * Text link styled as a button for secondary navigation (e.g. back to sign-in).
 */
export const linkButtonClass = 'text-sm text-slate-600 underline hover:text-slate-900'

export const alertClasses = {
  error: 'rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700',
  success: 'rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800',
  warning: 'rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800',
} as const
