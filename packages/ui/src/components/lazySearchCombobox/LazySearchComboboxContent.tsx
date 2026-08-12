/**
 * @file Dropdown panel for lazy searchable combobox fields.
 */

import { ComboboxButton, ComboboxOption, ComboboxOptions } from '@headlessui/react'
import {
  selectOptionClass,
  selectOptionsPanelClass,
  selectSearchInputClass,
  selectTriggerClass,
} from '../../styles/selectTokens'
import { ChevronDownIcon } from '../icons/ChevronDownIcon'
import { useLoadOnOpen } from './useLoadOnOpen'

type LazySearchComboboxContentProps<T> = {
  open: boolean
  placeholder: string
  searchPlaceholder: string
  loadingLabel: string
  emptyLabel: string
  noResultsLabel: string
  value: T | null
  options: T[]
  loading: boolean
  loaded: boolean
  onLoad: () => void | Promise<void>
  getOptionValue: (option: T) => string
  getOptionLabel: (option: T) => string
  isOptionDisabled?: (option: T) => boolean
  getOptionHint?: (option: T) => string | null
  query: string
  setQuery: (value: string) => void
  filteredOptions: T[]
}

/**
 * Trigger and options panel for a lazy searchable combobox.
 * @param props Open state, labels, options, and accessors.
 * @returns Combobox trigger and dropdown content.
 */
export function LazySearchComboboxContent<T>({
  open,
  placeholder,
  searchPlaceholder,
  loadingLabel,
  emptyLabel,
  noResultsLabel,
  value,
  options,
  loading,
  loaded,
  onLoad,
  getOptionValue,
  getOptionLabel,
  isOptionDisabled,
  getOptionHint,
  query,
  setQuery,
  filteredOptions,
}: LazySearchComboboxContentProps<T>) {
  useLoadOnOpen(open, onLoad)

  return (
    <div className="relative mt-1">
      <ComboboxButton className={selectTriggerClass}>
        <span className={value ? 'text-brand-primary' : 'text-brand-muted-subtle'}>
          {value ? getOptionLabel(value) : placeholder}
        </span>
        <ChevronDownIcon />
      </ComboboxButton>
      <ComboboxOptions portal={false} className={selectOptionsPanelClass}>
        {loading && <p className="px-3 py-2 text-sm text-brand-muted-subtle">{loadingLabel}</p>}
        {!loading && loaded && (
          <div className="sticky top-0 border-b border-brand-border bg-white p-2">
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              onMouseDown={(event) => event.stopPropagation()}
              placeholder={searchPlaceholder}
              className={selectSearchInputClass}
              autoFocus
            />
          </div>
        )}
        {!loading && loaded && options.length === 0 && (
          <p className="px-3 py-2 text-sm text-brand-muted-subtle">{emptyLabel}</p>
        )}
        {!loading && loaded && options.length > 0 && filteredOptions.length === 0 && (
          <p className="px-3 py-2 text-sm text-brand-muted-subtle">{noResultsLabel}</p>
        )}
        {!loading &&
          filteredOptions.map((option) => {
            const hint = getOptionHint?.(option)
            const optionLabel = hint ? `${getOptionLabel(option)} (${hint})` : getOptionLabel(option)

            return (
              <ComboboxOption
                key={getOptionValue(option)}
                value={option}
                disabled={isOptionDisabled?.(option)}
                className={selectOptionClass}
              >
                {optionLabel}
              </ComboboxOption>
            )
          })}
      </ComboboxOptions>
    </div>
  )
}
