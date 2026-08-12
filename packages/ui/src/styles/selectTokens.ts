/**
 * @file Shared Tailwind classes for Headless UI select primitives.
 */

export { fieldLabelClass } from './formTokens'

export const selectTriggerClass =
  'flex w-full items-center justify-between rounded-[9px] border border-brand-border bg-brand-surface px-3 py-2 text-left text-sm text-brand-primary outline-none transition focus:border-brand-accent focus:ring-2 focus:ring-brand-ring/20'

export const selectOptionsPanelClass =
  'absolute left-0 top-full z-20 mt-1 max-h-60 w-full overflow-auto rounded-[9px] border border-brand-border bg-brand-surface py-1 shadow-[0_4px_16px_rgb(22_25_30/0.06)]'

export const selectSearchInputClass =
  'w-full rounded-[9px] border border-brand-border bg-brand-surface px-3 py-2 text-sm text-brand-primary outline-none transition focus:border-brand-accent focus:ring-2 focus:ring-brand-ring/20'

export const selectOptionClass =
  'cursor-default px-3 py-2 text-sm text-brand-primary data-disabled:cursor-not-allowed data-disabled:text-brand-muted-subtle data-focus:bg-brand-surface-subtle data-selected:bg-brand-surface-subtle'
