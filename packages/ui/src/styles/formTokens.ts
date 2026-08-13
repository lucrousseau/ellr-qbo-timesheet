/**
 * @file Shared Tailwind classes for Headless UI form field primitives.
 */

export const fieldLabelClass = 'block text-sm font-medium text-brand-primary'

export const checkboxClass =
  'group flex size-4 shrink-0 items-center justify-center rounded border border-brand-border bg-brand-surface text-brand-on-accent transition data-checked:border-brand-accent data-checked:bg-brand-accent data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-brand-ring data-disabled:cursor-not-allowed data-disabled:opacity-50'

export const checkboxLabelClass = 'text-sm font-medium text-brand-primary'

/** Outer wrap for password fields (margin lives here so the toggle aligns to the input). */
export const passwordFieldWrapClass = 'relative mt-1'

/** Right padding so the visibility toggle does not overlap password text. */
export const passwordInputClass = 'pr-10'

/** Absolute wrapper for the password show/hide control. */
export const passwordToggleWrapClass = 'absolute inset-y-0 right-0 flex items-center pr-2'

/** Icon button chrome for password visibility toggle. */
export const passwordToggleButtonClass =
  'rounded-md p-1.5 text-brand-muted-subtle transition hover:text-brand-primary focus:outline-none data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-brand-ring'
