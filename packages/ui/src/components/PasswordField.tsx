/**
 * @file Accessible labeled password input with show/hide toggle.
 */

import { Field, Input, Label } from '@headlessui/react'
import { useState, type InputHTMLAttributes } from 'react'
import { fieldLabelClass, passwordFieldWrapClass, passwordInputClass } from '../styles/formTokens'
import { inputChromeClass } from '../styles/tokens'
import { PasswordVisibilityToggle } from './PasswordVisibilityToggle'

type PasswordFieldProps = {
  label: string
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'className' | 'type'>

/**
 * Labeled password input with an accessible show/hide control.
 * Requires LocaleProvider for toggle labels.
 * @param props Label text and native input attributes (type is always password when hidden).
 * @returns Accessible password form field.
 */
export function PasswordField({ label, ...inputProps }: PasswordFieldProps) {
  const [visible, setVisible] = useState(false)

  return (
    <Field>
      <Label className={fieldLabelClass}>{label}</Label>
      <div className={passwordFieldWrapClass}>
        <Input
          className={`${inputChromeClass} ${passwordInputClass}`}
          type={visible ? 'text' : 'password'}
          {...inputProps}
        />
        <PasswordVisibilityToggle visible={visible} onToggle={() => setVisible((current) => !current)} />
      </div>
    </Field>
  )
}
