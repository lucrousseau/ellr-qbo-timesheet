/**
 * @file Tests for recent draft entries section actions.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import { describe, expect, it, vi } from 'vitest'
import { RecentDraftEntriesSection } from './RecentDraftEntriesSection'

describe('RecentDraftEntriesSection', () => {
  it('shows bulk submit when drafts exist and wires draft handlers', async () => {
    const user = userEvent.setup()
    const onSubmitAllDrafts = vi.fn()
    const onEditDraft = vi.fn()
    const onSubmitDraft = vi.fn().mockResolvedValue(undefined)
    const onDeleteDraft = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <RecentDraftEntriesSection
          entries={[
            {
              id: 'local:1',
              timeEntryId: 1,
              startTime: '2026-07-29T09:00:00',
              endTime: '2026-07-29T11:00:00',
              durationSeconds: 7200,
              customerName: 'Acme Corp',
              serviceName: 'Programming',
              description: 'Draft work',
              isBillable: true,
              billableLocked: false,
              approvalStatus: 'draft',
            },
          ]}
          loading={false}
          error={null}
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
          displayTimezone="UTC"
          actionEntryId={null}
          editingEntry={null}
          submittingAll={false}
          onEditDraft={onEditDraft}
          onSubmitDraft={onSubmitDraft}
          onDeleteDraft={onDeleteDraft}
          onSubmitAllDrafts={onSubmitAllDrafts}
          onCloseEditor={() => {}}
          onSaveDraft={vi.fn()}
        />
      </LocaleProvider>,
    )

    await user.click(screen.getByRole('button', { name: /submit all drafts/i }))
    expect(onSubmitAllDrafts).toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Edit' }))
    expect(onEditDraft).toHaveBeenCalled()
  })
})
