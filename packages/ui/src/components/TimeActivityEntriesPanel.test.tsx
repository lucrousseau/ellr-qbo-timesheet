/**
 * @file Tests for the shared time activity entries table.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { TimeActivityEntriesPanel } from './TimeActivityEntriesPanel'

describe('TimeActivityEntriesPanel', () => {
  it('renders read-only rows and a load more action', () => {
    render(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          title="Recent entries"
          entries={[
            {
              id: '1',
              startTime: '2026-07-29T09:00:00',
              endTime: '2026-07-29T11:00:00',
              durationSeconds: 7200,
              customerName: 'Acme Corp',
              serviceName: 'Programming',
              description: 'Support',
              isBillable: true,
              billableLocked: false,
            },
          ]}
          loading={false}
          error={null}
          hasMore
          loadingMore={false}
          onLoadMore={() => {}}
        />
      </LocaleProvider>,
    )

    expect(screen.getByRole('heading', { name: 'Recent entries' })).toBeInTheDocument()
    expect(screen.getByText('Acme Corp')).toBeInTheDocument()
    expect(screen.getByText('Programming')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Load more' })).toBeInTheDocument()
  })

  it('renders loading, error, and empty states', () => {
    const { rerender } = render(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          entries={[]}
          loading
          error={null}
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Loading...')).toBeInTheDocument()

    rerender(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          entries={[]}
          loading={false}
          error="Sync failed"
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Sync failed')).toBeInTheDocument()

    rerender(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          entries={[]}
          loading={false}
          error={null}
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('No time entries yet.')).toBeInTheDocument()
  })

  it('renders billable labels and loading more state', () => {
    render(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          entries={[
            {
              id: '1',
              startTime: '',
              endTime: '',
              durationSeconds: 0,
              customerName: null,
              serviceName: null,
              description: '   ',
              isBillable: false,
              billableLocked: true,
            },
          ]}
          loading={false}
          error={null}
          hasMore
          loadingMore
          onLoadMore={() => {}}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Billed')).toBeInTheDocument()
    expect(screen.getAllByText('-').length).toBeGreaterThan(0)
    expect(screen.getByRole('button', { name: 'Loading...' })).toBeDisabled()
  })

  it('renders approval statuses and draft actions', async () => {
    const user = userEvent.setup()
    const onEditDraft = vi.fn()
    const onSubmitDraft = vi.fn().mockResolvedValue(undefined)
    const onDeleteDraft = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          title="Recent entries"
          entries={[
            {
              id: 'local:1',
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
            {
              id: 'local:2',
              startTime: '2026-07-28T09:00:00',
              endTime: '2026-07-28T10:00:00',
              durationSeconds: 3600,
              customerName: 'Beta',
              serviceName: 'Support',
              description: null,
              isBillable: false,
              billableLocked: false,
              approvalStatus: 'pending',
            },
            {
              id: 'local:3',
              startTime: '2026-07-27T09:00:00',
              endTime: '2026-07-27T10:00:00',
              durationSeconds: 3600,
              customerName: 'Gamma',
              serviceName: 'Design',
              description: null,
              isBillable: false,
              billableLocked: false,
              approvalStatus: 'approved',
            },
            {
              id: 'local:4',
              startTime: '2026-07-26T09:00:00',
              endTime: '2026-07-26T10:00:00',
              durationSeconds: 3600,
              customerName: 'Delta',
              serviceName: 'QA',
              description: null,
              isBillable: false,
              billableLocked: false,
              approvalStatus: 'rejected',
            },
          ]}
          loading={false}
          error={null}
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
          showApprovalStatus
          draftActions
          onEditDraft={onEditDraft}
          onSubmitDraft={onSubmitDraft}
          onDeleteDraft={onDeleteDraft}
        />
      </LocaleProvider>,
    )

    expect(screen.getByText('Draft')).toBeInTheDocument()
    expect(screen.getByText('Pending approval')).toBeInTheDocument()
    expect(screen.getByText('Approved')).toBeInTheDocument()
    expect(screen.getByText('Rejected')).toBeInTheDocument()

    await user.click(screen.getAllByRole('button', { name: 'Edit' })[0]!)
    expect(onEditDraft).toHaveBeenCalledWith(expect.objectContaining({ id: 'local:1' }))

    await user.click(screen.getAllByRole('button', { name: 'Submit for approval' })[0]!)
    expect(onSubmitDraft).toHaveBeenCalledWith('local:1')

    await user.click(screen.getAllByRole('button', { name: 'Delete' })[0]!)
    expect(onDeleteDraft).toHaveBeenCalledWith('local:1')
  })

  it('renders editable rows when editing is enabled', async () => {
    const user = userEvent.setup()
    const onSaveEntry = vi.fn().mockResolvedValue(undefined)

    render(
      <LocaleProvider>
        <TimeActivityEntriesPanel
          entries={[
            {
              id: '101',
              startTime: '2026-07-29T09:00:00',
              endTime: '2026-07-29T11:00:00',
              durationSeconds: 7200,
              customerName: 'Acme Corp',
              serviceName: 'Programming',
              description: 'Support',
              isBillable: true,
              billableLocked: false,
            },
          ]}
          loading={false}
          error={null}
          hasMore={false}
          loadingMore={false}
          onLoadMore={() => {}}
          editable
          onSaveEntry={onSaveEntry}
        />
      </LocaleProvider>,
    )

    await user.clear(screen.getByLabelText('Description'))
    await user.type(screen.getByLabelText('Description'), 'Updated')
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSaveEntry).toHaveBeenCalled()
  })
})
