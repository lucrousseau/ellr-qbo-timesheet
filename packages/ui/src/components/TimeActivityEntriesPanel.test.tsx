/**
 * @file Tests for the shared time activity entries table.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
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
})
