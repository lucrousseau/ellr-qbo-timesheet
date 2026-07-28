/**
 * @file User-facing API error messages keyed by locale.
 */

import type { UserLocale } from './locale'

type ErrorMessageKey =
  | 'invalid_credentials'
  | 'session_expired'
  | 'quickbooks_not_connected'
  | 'quickbooks_expired'
  | 'registration_disabled'
  | 'qbo_employee_not_configured'
  | 'admin_required'
  | 'email_not_verified'
  | 'access_denied'
  | 'qbo_employee_invalid'
  | 'qbo_employee_removed'
  | 'qbo_employee_email_missing'
  | 'qbo_employee_email_conflict'
  | 'qbo_insufficient_permissions'
  | 'invalid_data'
  | 'password_uncompromised'
  | 'quickbooks_busy'
  | 'network_unreachable'

const ERROR_MESSAGES: Record<UserLocale, Record<ErrorMessageKey, string>> = {
  en: {
    invalid_credentials: 'Invalid email or password.',
    session_expired: 'Session expired. Please sign in again.',
    quickbooks_not_connected: 'QuickBooks is not connected. Connect it from the admin app.',
    quickbooks_expired: 'QuickBooks connection expired. Reconnect it from the admin app.',
    registration_disabled: 'Registration disabled.',
    qbo_employee_not_configured: 'QuickBooks employee not configured. Contact an administrator.',
    admin_required: 'Administrator access required.',
    email_not_verified: 'Verify your email address before signing in.',
    access_denied: 'Access denied.',
    qbo_employee_invalid: 'QuickBooks employee not found.',
    qbo_employee_removed: 'Your QuickBooks employee access was removed. Contact an administrator.',
    qbo_employee_email_missing: 'QuickBooks employee has no email address configured.',
    qbo_employee_email_conflict: 'QuickBooks employee email is already used by another account.',
    qbo_insufficient_permissions: 'QuickBooks permissions are insufficient. Connect with a company administrator account.',
    invalid_data: 'Invalid data or QuickBooks error.',
    password_uncompromised: 'Choose a password that has not appeared in a known data breach.',
    quickbooks_busy: 'QuickBooks is busy. Please try again shortly.',
    network_unreachable: 'Unable to reach the Laravel API.',
  },
  fr: {
    invalid_credentials: 'Courriel ou mot de passe invalide.',
    session_expired: 'Session expirée. Veuillez vous reconnecter.',
    quickbooks_not_connected: 'QuickBooks n\'est pas connecté. Connectez-le depuis l\'application admin.',
    quickbooks_expired: 'La connexion QuickBooks a expiré. Reconnectez-la depuis l\'application admin.',
    registration_disabled: 'Inscription désactivée.',
    qbo_employee_not_configured: 'Employé QuickBooks non configuré. Contactez un administrateur.',
    admin_required: 'Accès administrateur requis.',
    email_not_verified: 'Vérifiez votre adresse courriel avant de vous connecter.',
    access_denied: 'Accès refusé.',
    qbo_employee_invalid: 'Employé QuickBooks introuvable.',
    qbo_employee_removed: 'Votre accès employé QuickBooks a été retiré. Contactez un administrateur.',
    qbo_employee_email_missing: 'L\'employé QuickBooks n\'a pas d\'adresse courriel configurée.',
    qbo_employee_email_conflict: 'Le courriel de l\'employé QuickBooks est déjà utilisé par un autre compte.',
    qbo_insufficient_permissions: 'Permissions QuickBooks insuffisantes. Connectez-vous avec un compte administrateur de la compagnie.',
    invalid_data: 'Données invalides ou erreur QuickBooks.',
    password_uncompromised: 'Choisissez un mot de passe qui ne figure pas dans une fuite de données connue.',
    quickbooks_busy: 'QuickBooks est occupé. Réessayez sous peu.',
    network_unreachable: 'Impossible de joindre l\'API Laravel.',
  },
}

/**
 * Returns a localized API error label for a known message key.
 * @param locale Active user locale.
 * @param key Stable error message key.
 * @returns Localized label.
 */
export function getLocalizedApiErrorMessage(locale: UserLocale, key: ErrorMessageKey): string {
  return ERROR_MESSAGES[locale][key]
}

export type { ErrorMessageKey }
