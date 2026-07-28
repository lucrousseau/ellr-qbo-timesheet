/**
 * @file Accessible labeled text input for forms.
 */

import { Field, Input, Label } from '@headlessui/react'
import type { InputHTMLAttributes } from 'react'
import { fieldLabelClass } from '../styles/selectTokens'
import { inputClass } from '../styles/tokens'

type TextFieldProps = {
  label: string
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'className'>

/**
 * Labeled text input wired for Headless UI field semantics.
 * @param props Label text and native input attributes.
 * @returns Accessible form field.
 */
export function TextField({ label, ...inputProps }: TextFieldProps) {
  return (
    <Field>
      <Label className={fieldLabelClass}>{label}</Label>
      <Input className={inputClass} {...inputProps} />
    </Field>
  )
}
