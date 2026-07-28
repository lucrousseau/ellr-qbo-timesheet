/**
 * @file Clears conflicting sessions when opening a password-set invite link.
 */

import { hasValidPasswordResetInvite } from '@ellr/api-client'
import { useEffect, useMemo, useState } from 'react'

type UsePasswordResetInviteGateOptions = {
  authLoading: boolean
  user: { email: string } | null
  handleLogout: () => Promise<void>
}

/**
 * Logs out an existing session so `/reset-password` invite links always show the guest form.
 * @param options Auth bootstrap state and logout handler.
 * @returns Guest-auth routing flags for the app shell.
 */
export function usePasswordResetInviteGate({
  authLoading,
  user,
  handleLogout,
}: UsePasswordResetInviteGateOptions) {
  const hasInvite = useMemo(() => hasValidPasswordResetInvite(), [])
  const [sessionCleared, setSessionCleared] = useState(!hasInvite)

  useEffect(() => {
    if (!hasInvite || authLoading || sessionCleared) {
      return
    }

    if (user) {
      void handleLogout().finally(() => setSessionCleared(true))

      return
    }

    setSessionCleared(true)
  }, [authLoading, handleLogout, hasInvite, sessionCleared, user])

  return {
    gateLoading: authLoading || (hasInvite && !sessionCleared),
    showGuestAuth: !user || (hasInvite && sessionCleared),
  }
}
