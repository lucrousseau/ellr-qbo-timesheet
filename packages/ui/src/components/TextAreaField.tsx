/**
 * @file Accessible labeled textarea for multi-line form inputs.
 */

import { Field, Label, Textarea } from '@headlessui/react'
import type { TextareaHTMLAttributes } from 'react'
import { fieldLabelClass } from '../styles/formTokens'
import { inputClass } from '../styles/tokens'

type TextAreaFieldProps = {
  label: string
} & Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'className'>

/**
 * Labeled textarea wired for Headless UI field semantics.
 * @param props Label text and native textarea attributes.
 * @returns Accessible multi-line form field.
 */
export function TextAreaField({ label, ...textareaProps }: TextAreaFieldProps) {
  return (
    <Field>
      <Label className={fieldLabelClass}>{label}</Label>
      <Textarea className={inputClass} {...textareaProps} />
    </Field>
  )
}
