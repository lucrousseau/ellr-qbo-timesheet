import type { ReactNode } from 'react'
import { alertClasses } from '../styles/tokens'

type AlertVariant = keyof typeof alertClasses

type AlertProps = {
  variant: AlertVariant
  children: ReactNode
  className?: string
}

export function Alert({ variant, children, className = '' }: AlertProps) {
  return <p className={`${alertClasses[variant]} ${className}`.trim()}>{children}</p>
}
