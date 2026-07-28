/**
 * @file Tests for API error resolution (status + code to stable keys).
 */

import { describe, expect, it } from 'vitest'
import { ApiError } from './api'
import { resolveApiError } from './apiErrorResolution'

describe('resolveApiError', () => {
  it('maps unauthorized responses to a session key', () => {
    expect(resolveApiError(new ApiError(401, 'API error: 401'))).toEqual({
      type: 'catalog',
      key: 'session_expired',
    })
  })

  it('maps invalid login credentials to the server message', () => {
    expect(
      resolveApiError(new ApiError(401, 'Invalid credentials.', 'invalid_credentials')),
    ).toEqual({
      type: 'server_message',
      message: 'Invalid credentials.',
    })
  })

  it('maps csrf mismatch responses to a session key', () => {
    expect(resolveApiError(new ApiError(419, 'Page Expired'))).toEqual({
      type: 'catalog',
      key: 'session_expired',
    })
  })

  it('maps forbidden responses to an access key', () => {
    expect(resolveApiError(new ApiError(403, 'API error: 403'))).toEqual({
      type: 'catalog',
      key: 'access_denied',
    })
  })

  it('maps quickbooks error codes on forbidden responses', () => {
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'quickbooks_not_connected')),
    ).toEqual({
      type: 'catalog',
      key: 'quickbooks_not_connected',
    })

    expect(resolveApiError(new ApiError(403, 'API error: 403', 'quickbooks_expired'))).toEqual({
      type: 'catalog',
      key: 'quickbooks_expired',
    })
  })

  it('maps registration disabled responses', () => {
    expect(resolveApiError(new ApiError(403, 'API error: 403', 'registration_disabled'))).toEqual({
      type: 'catalog',
      key: 'registration_disabled',
    })
  })

  it('maps administrator required responses', () => {
    expect(resolveApiError(new ApiError(403, 'API error: 403', 'admin_required'))).toEqual({
      type: 'catalog',
      key: 'admin_required',
    })
  })

  it('maps email not verified responses', () => {
    expect(resolveApiError(new ApiError(403, 'API error: 403', 'email_not_verified'))).toEqual({
      type: 'catalog',
      key: 'email_not_verified',
    })
  })

  it('maps qbo employee not configured responses', () => {
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'qbo_employee_not_configured')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_not_configured',
    })
  })

  it('maps qbo insufficient permissions responses', () => {
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'qbo_insufficient_permissions')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_insufficient_permissions',
    })
  })

  it('maps invalid qbo employee responses', () => {
    expect(resolveApiError(new ApiError(422, 'API error: 422', 'qbo_employee_invalid'))).toEqual({
      type: 'catalog',
      key: 'qbo_employee_invalid',
    })
  })

  it('maps validation responses to invalid data or server messages', () => {
    expect(resolveApiError(new ApiError(422, 'API error: 422'))).toEqual({
      type: 'catalog',
      key: 'invalid_data',
    })
    expect(resolveApiError(new ApiError(422, 'This password does not match our records.'))).toEqual({
      type: 'server_message',
      message: 'This password does not match our records.',
    })
  })

  it('maps uncompromised password validation to a password policy code', () => {
    expect(
      resolveApiError(
        new ApiError(
          422,
          'The given password has appeared in a data leak. Please choose a different password.',
        ),
      ),
    ).toEqual({
      type: 'password_policy',
      code: 'password_uncompromised',
    })
  })

  it('returns fallback for unknown errors', () => {
    expect(resolveApiError(new Error('boom'))).toEqual({ type: 'fallback' })
    expect(resolveApiError(new ApiError(500, 'API error: 500'))).toEqual({ type: 'fallback' })
  })

  it('maps service unavailable responses to a retry key', () => {
    expect(resolveApiError(new ApiError(503, 'API error: 503'))).toEqual({
      type: 'catalog',
      key: 'quickbooks_busy',
    })
  })

  it('maps network failures to an outage key', () => {
    expect(resolveApiError(new TypeError('failed to fetch'))).toEqual({
      type: 'catalog',
      key: 'network_unreachable',
    })
  })

  it('returns fallback for unrelated type errors', () => {
    expect(resolveApiError(new TypeError('Cannot read properties of undefined'))).toEqual({
      type: 'fallback',
    })
  })

  it('maps qbo employee email conflicts on forbidden and validation responses', () => {
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'qbo_employee_email_missing')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_email_missing',
    })
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'qbo_employee_removed')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_removed',
    })
    expect(
      resolveApiError(new ApiError(403, 'API error: 403', 'qbo_employee_email_conflict')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_email_conflict',
    })
    expect(
      resolveApiError(new ApiError(422, 'API error: 422', 'qbo_employee_email_missing')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_email_missing',
    })
    expect(
      resolveApiError(new ApiError(422, 'API error: 422', 'qbo_employee_email_conflict')),
    ).toEqual({
      type: 'catalog',
      key: 'qbo_employee_email_conflict',
    })
  })
})
