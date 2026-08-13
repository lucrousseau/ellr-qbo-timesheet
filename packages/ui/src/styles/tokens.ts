/**
 * @file Shared Tailwind class tokens for cards, inputs, and buttons.
 */

/** Shared input chrome without outer spacing (compose with margin utilities). */
export const inputChromeClass =
  'w-full rounded-[9px] border border-brand-border bg-brand-surface px-3 py-2 text-sm text-brand-primary outline-none transition focus:border-brand-accent focus:ring-2 focus:ring-brand-ring/20'

export const inputClass = `mt-1 ${inputChromeClass}`

export const pageMainClass = 'mx-auto max-w-3xl px-6 py-10'

export const pageTitleClass = 'text-3xl font-semibold tracking-tight text-brand-primary'

export const pageSubtitleClass = 'mt-2 text-brand-muted'

export const sectionTitleClass = 'text-xl font-medium text-brand-primary'

export const sectionTitleCompactClass = 'text-lg font-medium text-brand-primary'

export const sectionHelpClass = 'mt-2 text-sm text-brand-muted'

export const nestedPanelClass = 'rounded-[9px] border border-brand-border bg-brand-surface-subtle p-4 text-sm'

export const cardClass =
  'rounded-xl border border-brand-border bg-brand-surface p-6 shadow-[0_1px_2px_rgb(22_25_30/0.04)]'

/**
 * Primary action button chrome. Prefer the `Button` component in apps.
 * @deprecated Use `Button` from `@ellr/ui` instead.
 */
export const primaryButtonClass =
  'rounded-[9px] bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-on-accent transition hover:bg-brand-accent-hover disabled:opacity-50'

/**
 * Compact primary button for dense header chrome. Used via `Button` size `compact`.
 */
export const primaryButtonCompactClass =
  'rounded-[9px] bg-brand-accent px-3 py-2 text-sm font-semibold text-brand-on-accent transition hover:bg-brand-accent-hover disabled:opacity-50'

/**
 * Secondary action button chrome. Prefer the `Button` component in apps.
 * @deprecated Use `Button` from `@ellr/ui` instead.
 */
export const secondaryButtonClass =
  'rounded-[9px] border border-brand-border bg-brand-surface px-4 py-2.5 text-sm font-medium text-brand-primary transition hover:border-brand-accent/35 disabled:opacity-50'

/**
 * Compact secondary button for dense header chrome. Used via `Button` size `compact`.
 */
export const secondaryButtonCompactClass =
  'rounded-[9px] border border-brand-border bg-brand-surface px-3 py-2 text-sm font-medium text-brand-primary transition hover:border-brand-accent/35 disabled:opacity-50'

/**
 * Text link styled as a button for secondary navigation (e.g. back to sign-in).
 */
export const linkButtonClass = 'text-sm font-medium text-brand-muted underline hover:text-brand-accent'

export const alertClasses = {
  error: 'rounded-[9px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700',
  success: 'rounded-[9px] border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800',
  warning: 'rounded-[9px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800',
} as const
