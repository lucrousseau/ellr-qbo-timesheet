/**
 * @file Tests for clearing conflicting sessions on password reset invite links.
 */

import { renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePasswordResetInviteGate } from './usePasswordResetInviteGate'

vi.mock('@ellr/api-client', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@ellr/api-client')>()

  return {
    ...actual,
    hasValidPasswordResetInvite: vi.fn(),
  }
})

import { hasValidPasswordResetInvite } from '@ellr/api-client'

type InviteGateProps = {
  authLoading: boolean
  user: { email: string } | null
}

describe('usePasswordResetInviteGate', () => {
  const handleLogout = vi.fn().mockResolvedValue(undefined)

  beforeEach(() => {
    vi.mocked(hasValidPasswordResetInvite).mockReset()
    handleLogout.mockClear()
  })

  it('passes through when there is no invite link', () => {
    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(false)

    const { result } = renderHook(() =>
      usePasswordResetInviteGate({
        authLoading: false,
        user: { email: 'user@example.com' },
        handleLogout,
      }),
    )

    expect(result.current.gateLoading).toBe(false)
    expect(result.current.showGuestAuth).toBe(false)
    expect(handleLogout).not.toHaveBeenCalled()
  })

  it('logs out an existing session before showing the reset form', async () => {
    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(true)

    const initialProps: InviteGateProps = { authLoading: false, user: { email: 'user@example.com' } }

    const { result, rerender } = renderHook(
      ({ authLoading, user }: InviteGateProps) =>
        usePasswordResetInviteGate({
          authLoading,
          user,
          handleLogout,
        }),
      { initialProps },
    )

    expect(result.current.gateLoading).toBe(true)

    await waitFor(() => {
      expect(handleLogout).toHaveBeenCalledTimes(1)
      expect(result.current.gateLoading).toBe(false)
    })

    rerender({ authLoading: false, user: null })

    expect(result.current.showGuestAuth).toBe(true)
  })

  it('shows the reset form immediately when no session exists', async () => {
    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(true)

    const { result } = renderHook(() =>
      usePasswordResetInviteGate({
        authLoading: false,
        user: null,
        handleLogout,
      }),
    )

    await waitFor(() => {
      expect(result.current.gateLoading).toBe(false)
      expect(result.current.showGuestAuth).toBe(true)
    })
    expect(handleLogout).not.toHaveBeenCalled()
  })

  it('shows the dashboard after sign-in even when the invite URL was opened first', async () => {
    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(true)

    const initialProps: InviteGateProps = { authLoading: false, user: null }

    const { result, rerender } = renderHook(
      ({ authLoading, user }: InviteGateProps) =>
        usePasswordResetInviteGate({
          authLoading,
          user,
          handleLogout,
        }),
      { initialProps },
    )

    await waitFor(() => {
      expect(result.current.showGuestAuth).toBe(true)
    })

    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(false)

    rerender({ authLoading: false, user: { email: 'user@example.com' } })

    expect(result.current.showGuestAuth).toBe(false)
  })

  it('waits for auth bootstrap before clearing the session', async () => {
    vi.mocked(hasValidPasswordResetInvite).mockReturnValue(true)

    const initialProps: InviteGateProps = { authLoading: true, user: { email: 'user@example.com' } }

    const { result, rerender } = renderHook(
      ({ authLoading, user }: InviteGateProps) =>
        usePasswordResetInviteGate({
          authLoading,
          user,
          handleLogout,
        }),
      { initialProps },
    )

    expect(result.current.gateLoading).toBe(true)
    expect(handleLogout).not.toHaveBeenCalled()

    rerender({ authLoading: false, user: { email: 'user@example.com' } })

    await waitFor(() => {
      expect(handleLogout).toHaveBeenCalledTimes(1)
    })
  })
})
