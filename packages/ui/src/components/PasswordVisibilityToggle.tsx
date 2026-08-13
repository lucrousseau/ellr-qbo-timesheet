/**
 * @file Icon button that toggles password field visibility.
 */

import { Button as HeadlessButton } from '@headlessui/react'
import { useLocale } from '../i18n/LocaleProvider'
import { passwordToggleButtonClass, passwordToggleWrapClass } from '../styles/formTokens'
import { EyeIcon } from './icons/EyeIcon'
import { EyeSlashIcon } from './icons/EyeSlashIcon'

type PasswordVisibilityToggleProps = {
  visible: boolean
  onToggle: () => void
}

/**
 * Show/hide control for password inputs; requires LocaleProvider.
 * @param props Current visibility and toggle handler.
 * @returns Absolutely positioned icon button.
 */
export function PasswordVisibilityToggle({ visible, onToggle }: PasswordVisibilityToggleProps) {
  const { t } = useLocale()
  const label = visible ? t('common.hidePassword') : t('common.showPassword')

  return (
    <div className={passwordToggleWrapClass}>
      <HeadlessButton
        type="button"
        className={passwordToggleButtonClass}
        aria-label={label}
        aria-pressed={visible}
        onClick={onToggle}
      >
        {visible ? <EyeSlashIcon /> : <EyeIcon />}
      </HeadlessButton>
    </div>
  )
}
