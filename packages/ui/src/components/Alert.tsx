/**
 * @file Inline alert banner for errors and success notices.
 */

import type { ReactNode } from 'react'
import { alertClasses } from '../styles/tokens'

type AlertVariant = keyof typeof alertClasses

type AlertProps = {
  variant: AlertVariant
  children: ReactNode
  className?: string
}

/**
 * Message banner (error, success, or warning).
 * @param props.variant Visual style for the message.
 * @param props.children Message content.
 * @param props.className Additional Tailwind classes.
 * @returns Styled paragraph for the variant.
 */
export function Alert({ variant, children, className = '' }: AlertProps) {
  return <p className={`${alertClasses[variant]} ${className}`.trim()}>{children}</p>
}
