/**
 * @file Styled action button with Ellr design system variants.
 */

import { Button as HeadlessButton } from '@headlessui/react'
import type { ButtonHTMLAttributes } from 'react'
import { dangerButtonClass } from '../styles/dialogTokens'
import {
  iconButtonClass,
  iconDangerButtonClass,
  linkButtonClass,
  primaryButtonClass,
  primaryButtonCompactClass,
  secondaryButtonClass,
  secondaryButtonCompactClass,
} from '../styles/tokens'

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'link'
type ButtonSize = 'default' | 'compact' | 'icon'

type ButtonProps = {
  variant?: ButtonVariant
  size?: ButtonSize
} & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'className'>

/**
 * Resolves Tailwind classes for a button variant and optional compact size.
 * @param variant Visual style (primary, secondary, danger, or text link).
 * @param size Padding density; compact restores header-scale chrome; icon is square.
 * @returns Combined class string for the Headless button.
 */
function buttonClassFor(variant: ButtonVariant, size: ButtonSize): string {
  if (size === 'icon') {
    return variant === 'danger' ? iconDangerButtonClass : iconButtonClass
  }

  if (variant === 'link') {
    return linkButtonClass
  }

  if (variant === 'danger') {
    return dangerButtonClass
  }

  if (variant === 'primary') {
    return size === 'compact' ? primaryButtonCompactClass : primaryButtonClass
  }

  return size === 'compact' ? secondaryButtonCompactClass : secondaryButtonClass
}

/**
 * Ellr-styled button built on Headless UI for consistent focus and disabled states.
 * @param props Button variant, size, and native button attributes.
 * @returns Accessible action button.
 */
export function Button({
  variant = 'primary',
  size = 'default',
  disabled,
  ...buttonProps
}: ButtonProps) {
  return (
    <HeadlessButton
      className={buttonClassFor(variant, size)}
      disabled={disabled}
      {...buttonProps}
    />
  )
}
