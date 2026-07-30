/**
 * @file Tests for combined user preference API helpers.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiFetch } from './api'
import { updateUserPreferences, type User } from './auth'

vi.mock('./api', () => ({
  apiFetch: vi.fn(),
}))

describe('updateUserPreferences', () => {
  beforeEach(() => {
    vi.mocked(apiFetch).mockReset()
  })

  it('patches locale and timezone in one request', async () => {
    const updatedUser: User = {
      id: 1,
      name: 'Jane',
      email: 'jane@example.com',
      locale: 'fr',
      timezone: null,
    }
    vi.mocked(apiFetch).mockResolvedValue({ user: updatedUser })

    await expect(
      updateUserPreferences({ locale: 'fr', timezone: null }),
    ).resolves.toEqual(updatedUser)

    expect(apiFetch).toHaveBeenCalledWith('/user/preferences', {
      method: 'PATCH',
      body: JSON.stringify({ locale: 'fr', timezone: null }),
    })
  })
})
