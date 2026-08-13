/**
 * @file State helper for opening QuickBooks sync group drill-down dialogs.
 */

import { useEffect, useState } from 'react'

/**
 * Reads `?sync_group=` from the URL and manages the selected sync group dialog.
 * @returns Selected public id, open handlers, and query-param bootstrap.
 */
export function useTimeEntrySyncGroupViewer(): {
  syncGroupPublicId: string | null
  openSyncGroup: (publicId: string) => void
  closeSyncGroup: () => void
} {
  const [syncGroupPublicId, setSyncGroupPublicId] = useState<string | null>(null)

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const fromQuery = params.get('sync_group')?.trim()

    if (fromQuery) {
      setSyncGroupPublicId(fromQuery)
    }
  }, [])

  return {
    syncGroupPublicId,
    openSyncGroup: (publicId: string) => {
      setSyncGroupPublicId(publicId)
    },
    closeSyncGroup: () => {
      setSyncGroupPublicId(null)

      const url = new URL(window.location.href)
      if (url.searchParams.has('sync_group')) {
        url.searchParams.delete('sync_group')
        window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`)
      }
    },
  }
}
