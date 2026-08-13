/**
 * @file Tests for recent draft entries section actions and status filter.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocaleProvider } from '@ellr/ui'
import { describe, expect, it, vi } from 'vitest'
import type { TimeActivityRow } from '../hooks/useDraftTimeEntryActions'
import { RecentDraftEntriesSection } from './RecentDraftEntriesSection'

function entry(
  overrides: Partial<TimeActivityRow> & Pick<TimeActivityRow, 'id' | 'approvalStatus'>,
): TimeActivityRow {
  return {
    timeEntryId: 1,
    startTime: '2026-07-29T09:00:00',
    endTime: '2026-07-29T11:00:00',
    durationSeconds: 7200,
    customerName: 'Acme Corp',
    serviceName: 'Programming',
    description: 'Draft work',
    isBillable: true,
    billableLocked: false,
    ...overrides,
  }
}

const baseProps = {
  loading: false,
  error: null,
  hasMore: false,
  loadingMore: false,
  onLoadMore: () => {},
  displayTimezone: 'UTC',
  actionEntryId: null,
  editingEntry: null,
  submittingSelected: false,
  onEditDraft: vi.fn(),
  onDeleteDraft: vi.fn().mockResolvedValue(undefined),
  onSubmitSelectedDrafts: vi.fn(),
  onCloseEditor: () => {},
  onSaveDraft: vi.fn(),
}

describe('RecentDraftEntriesSection', () => {
  it('submits selected drafts and opens edit from the pencil icon', async () => {
    const user = userEvent.setup()
    const onSubmitSelectedDrafts = vi.fn()
    const onEditDraft = vi.fn()

    render(
      <LocaleProvider>
        <RecentDraftEntriesSection
          {...baseProps}
          entries={[
            entry({ id: 'local:1', timeEntryId: 1, approvalStatus: 'draft', customerName: 'Draft A' }),
            entry({ id: 'local:2', timeEntryId: 2, approvalStatus: 'draft', customerName: 'Draft B' }),
          ]}
          onSubmitSelectedDrafts={onSubmitSelectedDrafts}
          onEditDraft={onEditDraft}
        />
      </LocaleProvider>,
    )

    expect(screen.getByRole('table')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /submit 0 selected/i })).toBeDisabled()

    await user.click(screen.getByLabelText('Select all rows'))
    expect(screen.getByRole('button', { name: /submit 2 selected/i })).toBeEnabled()

    await user.click(screen.getByRole('button', { name: /submit 2 selected/i }))
    expect(onSubmitSelectedDrafts).toHaveBeenCalledWith(['local:1', 'local:2'])

    await user.click(screen.getAllByRole('button', { name: 'Edit details' })[0]!)
    expect(onEditDraft).toHaveBeenCalledWith(expect.objectContaining({ id: 'local:1' }))
  })

  it('hides approved entries by default and shows them when filtered', async () => {
    const user = userEvent.setup()

    render(
      <LocaleProvider>
        <RecentDraftEntriesSection
          {...baseProps}
          entries={[
            entry({
              id: 'local:1',
              approvalStatus: 'draft',
              customerName: 'Draft Client',
            }),
            entry({
              id: 'qbo:2',
              approvalStatus: 'approved',
              customerName: 'Approved Client',
              serviceName: 'Hours',
            }),
          ]}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Draft Client')).toBeInTheDocument()
    expect(screen.queryByText('Approved Client')).not.toBeInTheDocument()

    await user.click(screen.getByLabelText(/^status$/i))
    await user.click(screen.getByRole('option', { name: 'Approved' }))

    expect(screen.getByText('Approved Client')).toBeInTheDocument()
    expect(screen.queryByText('Draft Client')).not.toBeInTheDocument()
  })
})
