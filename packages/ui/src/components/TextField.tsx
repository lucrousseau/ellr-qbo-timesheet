/**
 * @file Accessible labeled text input for forms.
 */

import { Description, Field, Input, Label } from '@headlessui/react'
import type { InputHTMLAttributes } from 'react'
import { fieldHintClass, fieldLabelClass } from '../styles/formTokens'
import { inputClass } from '../styles/tokens'

type TextFieldProps = {
  label: string
  /** Optional helper text shown below the input. */
  hint?: string
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'className'>

/**
 * Labeled text input wired for Headless UI field semantics.
 * @param props Label text, optional hint, and native input attributes.
 * @returns Accessible form field.
 */
export function TextField({ label, hint, ...inputProps }: TextFieldProps) {
  return (
    <Field>
      <Label className={fieldLabelClass}>{label}</Label>
      <Input className={inputClass} {...inputProps} />
      {hint ? <Description className={fieldHintClass}>{hint}</Description> : null}
    </Field>
  )
}
