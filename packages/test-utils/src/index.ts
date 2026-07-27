/**
 * @file Shared Vitest helpers and auth fixtures for frontend tests.
 */

import { screen } from '@testing-library/react'
import type { UserEvent } from '@testing-library/user-event'
import { expect, vi } from 'vitest'

/** Authenticated user with a QBO employee for component tests. */
export const authenticatedUser = {
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  qbo_employee_ref: '7',
  qbo_employee_name: 'Jane Doe',
}

type ApiClientMockOverrides = Record<string, unknown>

/**
 * Builds a Vitest mock of `@ellr/api-client` while keeping real exports.
 * @param overrides Mock functions to merge (e.g. `createTimeActivity`).
 * @returns Mocked module for `vi.mock('@ellr/api-client', ...)`.
 */
export async function buildApiClientMock(overrides: ApiClientMockOverrides = {}) {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    fetchCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  }
}

/**
 * Fills and submits the sign-in form in an RTL test.
 * @param user `userEvent` instance from the test.
 * @param credentials Optional email and password.
 * @returns Promise resolved after clicking Sign in.
 */
export async function fillLoginForm(
  user: UserEvent,
  credentials: { email?: string; password?: string } = {},
) {
  await user.type(screen.getByLabelText(/email/i), credentials.email ?? 'test@example.com')
  await user.type(screen.getByLabelText(/password/i), credentials.password ?? 'password')
  await user.click(screen.getByRole('button', { name: /sign in/i }))
}

/**
 * Asserts Tailwind classes on a message for the expected type.
 * @param element DOM node for the displayed message.
 * @param type Expected variant (`error`, `success`, `warning`).
 */
export function expectMessageClasses(element: HTMLElement, type: 'error' | 'success' | 'warning') {
  if (type === 'error') {
    expect(element.className).toContain('bg-red-50')
    expect(element.className).toContain('text-red-700')
    expect(element.className).not.toContain('bg-green-50')
  } else if (type === 'success') {
    expect(element.className).toContain('bg-green-50')
    expect(element.className).toContain('text-green-800')
    expect(element.className).not.toContain('bg-red-50')
  } else {
    expect(element.className).toContain('bg-amber-50')
    expect(element.className).toContain('text-amber-800')
  }
}
