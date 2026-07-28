/**
 * @file Searchable combobox that loads API-backed options on first open.
 */

import { Combobox, Field, Label } from '@headlessui/react'
import { useMemo, useState } from 'react'
import { fieldLabelClass } from '../styles/selectTokens'
import { LazySearchComboboxContent } from './lazySearchCombobox/LazySearchComboboxContent'
import { compareOptionBy } from './select/compareOptionBy'

type LazySearchComboboxProps<T> = {
  label: string
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
  onClose?: () => void
  onChange: (value: T | null) => void
  getOptionValue: (option: T) => string
  getOptionLabel: (option: T) => string
  isOptionDisabled?: (option: T) => boolean
  getOptionHint?: (option: T) => string | null
}

/**
 * Accessible searchable select that fetches options when opened and filters client-side.
 * @param props Labels, options, loading state, and option accessors.
 * @returns Lazy-loaded combobox field.
 */
export function LazySearchCombobox<T>({
  label,
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
  onClose,
  onChange,
  getOptionValue,
  getOptionLabel,
  isOptionDisabled,
  getOptionHint,
}: LazySearchComboboxProps<T>) {
  const [query, setQuery] = useState('')

  const filteredOptions = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase()

    if (normalizedQuery === '') {
      return options
    }

    return options.filter((option) => getOptionLabel(option).toLowerCase().includes(normalizedQuery))
  }, [getOptionLabel, options, query])

  return (
    <Combobox
      value={value}
      by={compareOptionBy(getOptionValue)}
      onChange={onChange}
      onClose={() => {
        setQuery('')
        onClose?.()
      }}
    >
      {({ open }) => (
        <Field>
          <Label className={fieldLabelClass}>{label}</Label>
          <LazySearchComboboxContent
            open={open}
            placeholder={placeholder}
            searchPlaceholder={searchPlaceholder}
            loadingLabel={loadingLabel}
            emptyLabel={emptyLabel}
            noResultsLabel={noResultsLabel}
            value={value}
            options={options}
            loading={loading}
            loaded={loaded}
            onLoad={onLoad}
            getOptionValue={getOptionValue}
            getOptionLabel={getOptionLabel}
            isOptionDisabled={isOptionDisabled}
            getOptionHint={getOptionHint}
            query={query}
            setQuery={setQuery}
            filteredOptions={filteredOptions}
          />
        </Field>
      )}
    </Combobox>
  )
}
