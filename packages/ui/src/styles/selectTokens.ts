/**
 * @file Shared Tailwind classes for Headless UI select primitives.
 */

export { fieldLabelClass } from './formTokens'

export const selectTriggerClass =
  'flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200'

export const selectOptionsPanelClass =
  'absolute left-0 top-full z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg'

export const selectSearchInputClass =
  'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200'

export const selectOptionClass =
  'cursor-default px-3 py-2 text-sm text-slate-900 data-disabled:cursor-not-allowed data-disabled:text-slate-400 data-focus:bg-slate-100 data-selected:bg-slate-50'
