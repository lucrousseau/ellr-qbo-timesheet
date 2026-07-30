/**
 * @file Masked text input built on IMask for reusable formatted entry.
 */

import { useIMask } from 'react-imask'
import type { FactoryOpts } from 'imask'
import { useEffect, type InputHTMLAttributes, type Ref } from 'react'

type MaskedInputProps = {
  mask: string | FactoryOpts
  value: string
  onAccept: (value: string) => void
  inputRef?: Ref<HTMLInputElement>
  className?: string
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange' | 'className' | 'mask'>

/**
 * Text input with an IMask pattern; use `onAccept` instead of `onChange`.
 * @param props Mask options, formatted value, and native input attributes.
 * @returns Masked input element.
 */
export function MaskedInput({
  mask,
  value,
  onAccept,
  inputRef,
  className,
  ...inputProps
}: MaskedInputProps) {
  const maskOptions = typeof mask === 'string' ? { mask } : mask
  const { ref, setValue } = useIMask(maskOptions, {
    onAccept: (maskedValue) => onAccept(String(maskedValue)),
  })

  useEffect(() => {
    setValue(value)
  }, [setValue, value])

  const setRefs = (element: HTMLInputElement | null) => {
    ref.current = element

    if (inputRef) {
      if (typeof inputRef === 'function') {
        inputRef(element)
      } else {
        inputRef.current = element
      }
    }
  }

  return <input ref={setRefs} className={className} {...inputProps} />
}
