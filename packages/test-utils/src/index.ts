import { screen } from '@testing-library/react'
import type { UserEvent } from '@testing-library/user-event'
import { expect } from 'vitest'
import { vi } from 'vitest'

export const authenticatedUser = {
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  qbo_employee_ref: '7',
  qbo_employee_name: 'Jane Doe',
}

export async function buildApiClientMock() {
  const actual = await vi.importActual<typeof import('@ellr/api-client')>('@ellr/api-client')

  return {
    ...actual,
    apiFetch: vi.fn(),
    fetchCurrentUser: vi.fn(),
    login: vi.fn(),
    logout: vi.fn().mockResolvedValue(undefined),
  }
}

export async function fillLoginForm(
  user: UserEvent,
  credentials: { email?: string; password?: string } = {},
) {
  await user.type(screen.getByLabelText(/courriel/i), credentials.email ?? 'test@example.com')
  await user.type(screen.getByLabelText(/mot de passe/i), credentials.password ?? 'password')
  await user.click(screen.getByRole('button', { name: /se connecter/i }))
}

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
