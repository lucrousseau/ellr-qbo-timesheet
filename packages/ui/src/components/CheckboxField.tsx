/**
 * @file Accessible labeled checkbox for boolean form fields.
 */

import { Checkbox, Field, Label } from '@headlessui/react'
import { checkboxClass, checkboxLabelClass } from '../styles/formTokens'

type CheckboxFieldProps = {
  label: string
  checked: boolean
  disabled?: boolean
  onChange: (checked: boolean) => void
  /** When true, keeps the label for assistive tech only. */
  labelVisuallyHidden?: boolean
}

/**
 * Labeled checkbox wired for Headless UI field semantics.
 * @param props Label text, checked state, and change handler.
 * @returns Accessible checkbox field.
 */
export function CheckboxField({
  label,
  checked,
  disabled = false,
  onChange,
  labelVisuallyHidden = false,
}: CheckboxFieldProps) {
  return (
    <Field className={labelVisuallyHidden ? 'inline-flex items-center' : 'flex items-center gap-3'}>
      <Checkbox
        checked={checked}
        disabled={disabled}
        onChange={onChange}
        className={checkboxClass}
      >
        <svg viewBox="0 0 14 14" fill="none" className="size-3 stroke-white opacity-0 group-data-checked:opacity-100">
          <path d="M3 7.5 5.5 10 11 4" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </Checkbox>
      <Label className={labelVisuallyHidden ? 'sr-only' : checkboxLabelClass}>{label}</Label>
    </Field>
  )
}
