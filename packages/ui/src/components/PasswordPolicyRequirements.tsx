/**
 * @file Shared password policy requirement hints for new-password forms.
 */

import { getPasswordRequirementLabels } from '../i18n/passwordPolicyMessages'
import { useLocale } from '../i18n/LocaleProvider'

/**
 * Renders localized password strength requirements from the shared policy.
 * @returns Password policy hint list for new-password flows.
 */
export function PasswordPolicyRequirements() {
  const { t, locale } = useLocale()
  const requirementLabels = getPasswordRequirementLabels(locale)

  return (
    <div className="rounded-md border border-brand-border bg-brand-surface-subtle p-3 text-sm text-brand-muted">
      <p className="font-medium text-brand-primary">{t('auth.passwordPolicy.requirementsTitle')}</p>
      <ul className="mt-2 list-disc space-y-1 pl-5">
        {requirementLabels.map((label) => (
          <li key={label}>{label}</li>
        ))}
      </ul>
    </div>
  )
}
