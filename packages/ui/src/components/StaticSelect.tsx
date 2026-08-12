/**
 * @file Static listbox select for short, client-side option lists.
 */

import { Field, Label, Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react'
import {
  fieldLabelClass,
  selectOptionClass,
  selectOptionsPanelClass,
  selectTriggerClass,
} from '../styles/selectTokens'
import { ChevronDownIcon } from './icons/ChevronDownIcon'
import { compareOptionBy } from './select/compareOptionBy'

type StaticSelectProps<T> = {
  label: string
  placeholder?: string
  value: T | null
  options: readonly T[]
  onChange: (value: T) => void
  getOptionValue: (option: T) => string
  getOptionLabel: (option: T) => string
  isOptionDisabled?: (option: T) => boolean
}

/**
 * Accessible select for static option lists (locale, enums, short pickers).
 * @param props Label, options, and value accessors.
 * @returns Listbox-based select field.
 */
export function StaticSelect<T>({
  label,
  placeholder,
  value,
  options,
  onChange,
  getOptionValue,
  getOptionLabel,
  isOptionDisabled,
}: StaticSelectProps<T>) {
  return (
    <Listbox value={value ?? undefined} by={compareOptionBy(getOptionValue)} onChange={onChange}>
      <Field>
        <Label className={fieldLabelClass}>{label}</Label>
        <div className="relative mt-1">
          <ListboxButton className={selectTriggerClass}>
            <span className={value ? 'text-brand-primary' : 'text-brand-muted-subtle'}>
              {value ? getOptionLabel(value) : (placeholder ?? '')}
            </span>
            <ChevronDownIcon />
          </ListboxButton>
          <ListboxOptions portal={false} className={selectOptionsPanelClass}>
            {options.map((option) => (
              <ListboxOption
                key={getOptionValue(option)}
                value={option}
                disabled={isOptionDisabled?.(option)}
                className={selectOptionClass}
              >
                {getOptionLabel(option)}
              </ListboxOption>
            ))}
          </ListboxOptions>
        </div>
      </Field>
    </Listbox>
  )
}
