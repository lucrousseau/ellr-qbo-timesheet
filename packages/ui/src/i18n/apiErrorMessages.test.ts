/**
 * @file Tests for localized API error labels.
 */

import { describe, expect, it } from 'vitest'
import { API_ERROR_MESSAGE_KEYS, ApiError } from '@ellr/api-client'
import { getApiErrorMessage, translateApiError } from './apiErrorMessages'

describe('getApiErrorMessage', () => {
  it('maps unauthorized responses to a session message', () => {
    expect(getApiErrorMessage(new ApiError(401, 'API error: 401'), 'fallback')).toBe(
      'Session expired. Please sign in again.',
    )
  })

  it('maps invalid login credentials to the server message', () => {
    expect(
      getApiErrorMessage(new ApiError(401, 'Invalid credentials.', 'invalid_credentials'), 'fallback'),
    ).toBe('Invalid credentials.')
    expect(
      getApiErrorMessage(
        new ApiError(401, 'Identifiants invalides.', 'invalid_credentials'),
        'fallback',
        'en',
      ),
    ).toBe('Identifiants invalides.')
  })

  it('maps csrf mismatch responses to a session message', () => {
    expect(getApiErrorMessage(new ApiError(419, 'Page Expired'), 'fallback')).toBe(
      'Session expired. Please sign in again.',
    )
  })

  it('maps forbidden responses to an access message', () => {
    expect(getApiErrorMessage(new ApiError(403, 'API error: 403'), 'fallback')).toBe(
      'Access denied.',
    )
  })

  it('maps quickbooks error codes on forbidden responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'quickbooks_not_connected'), 'fallback'),
    ).toBe('QuickBooks is not connected. Connect it from the admin app.')

    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'quickbooks_expired'), 'fallback'),
    ).toBe('QuickBooks connection expired. Reconnect it from the admin app.')
  })

  it('maps registration disabled responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'registration_disabled'), 'fallback'),
    ).toBe('Registration disabled.')
  })

  it('maps administrator required responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'admin_required'), 'fallback'),
    ).toBe('Administrator access required.')
  })

  it('maps organization required responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'organization_required'), 'fallback'),
    ).toBe('Organization membership is required for this account.')

    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'organization_required'), 'fallback', 'fr'),
    ).toBe('Une organisation est requise pour ce compte.')
  })

  it('maps email not verified responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'email_not_verified'), 'fallback'),
    ).toBe('Verify your email address before signing in.')
  })

  it('maps invalid qbo employee responses', () => {
    expect(
      getApiErrorMessage(new ApiError(422, 'API error: 422', 'qbo_employee_invalid'), 'fallback'),
    ).toBe('QuickBooks employee not found.')
  })

  it('maps validation responses to a quickbooks message', () => {
    expect(getApiErrorMessage(new ApiError(422, 'API error: 422'), 'fallback')).toBe(
      'Invalid data or QuickBooks error.',
    )
    expect(getApiErrorMessage(new ApiError(422, 'This password does not match our records.'), 'fallback')).toBe(
      'This password does not match our records.',
    )
  })

  it('maps uncompromised password validation to a localized message', () => {
    expect(
      getApiErrorMessage(
        new ApiError(
          422,
          'The given password has appeared in a data leak. Please choose a different password.',
        ),
        'fallback',
      ),
    ).toBe('Choose a password that has not appeared in a known data breach.')

    expect(
      getApiErrorMessage(
        new ApiError(
          422,
          'La valeur du champ password est apparue dans une fuite de données. Veuillez choisir une valeur différente.',
        ),
        'fallback',
        'fr',
      ),
    ).toBe('Choisissez un mot de passe qui ne figure pas dans une fuite de données connue.')
  })

  it('uses the fallback for unknown errors', () => {
    expect(getApiErrorMessage(new Error('boom'), 'fallback')).toBe('fallback')
  })

  it('maps service unavailable responses to a retry message', () => {
    expect(getApiErrorMessage(new ApiError(503, 'API error: 503'), 'fallback')).toBe(
      'QuickBooks is busy. Please try again shortly.',
    )
  })

  it('maps network failures to an api outage message', () => {
    expect(getApiErrorMessage(new TypeError('failed to fetch'), 'fallback')).toBe(
      'Unable to reach the Laravel API.',
    )
  })

  it('uses the fallback for unrelated type errors', () => {
    expect(getApiErrorMessage(new TypeError('Cannot read properties of undefined'), 'fallback')).toBe(
      'fallback',
    )
  })

  it('normalizes unsupported locales before translating catalog errors', () => {
    expect(
      getApiErrorMessage(new ApiError(401, 'API error: 401'), 'fallback', 'de' as 'en'),
    ).toBe('Session expired. Please sign in again.')
  })

  it('uses the fallback for unmapped api error status codes', () => {
    expect(getApiErrorMessage(new ApiError(500, 'API error: 500'), 'Sign-out failed.')).toBe(
      'Sign-out failed.',
    )
  })

  it('maps qbo employee not configured responses', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'qbo_employee_not_configured'), 'fallback'),
    ).toBe('QuickBooks employee not configured. Contact an administrator.')
  })

  it('maps business error codes to french copy when locale is fr', () => {
    expect(
      getApiErrorMessage(new ApiError(403, 'API error: 403', 'admin_required'), 'fallback', 'fr'),
    ).toBe('Accès administrateur requis.')
  })

  it('maps qbo insufficient permissions responses', () => {
    expect(
      getApiErrorMessage(
        new ApiError(403, 'API error: 403', 'qbo_insufficient_permissions'),
        'fallback',
        'fr',
      ),
    ).toBe(
      'Permissions QuickBooks insuffisantes. Connectez-vous avec un compte administrateur de la compagnie.',
    )
  })

  it('defines every api error key in both locale catalogs', () => {
    for (const key of API_ERROR_MESSAGE_KEYS) {
      expect(translateApiError('en', key)).not.toBe(`api.errors.${key}`)
      expect(translateApiError('fr', key)).not.toBe(`api.errors.${key}`)
      expect(translateApiError('en', key).length).toBeGreaterThan(0)
      expect(translateApiError('fr', key).length).toBeGreaterThan(0)
    }
  })
})
