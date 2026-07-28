/**
 * @file Tests for lazy searchable combobox.
 */

import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LazySearchCombobox } from './LazySearchCombobox'

type Fruit = { id: string; name: string }

const fruits: Fruit[] = [
  { id: '1', name: 'Apple' },
  { id: '2', name: 'Banana' },
]

const labels = {
  label: 'Fruit',
  placeholder: 'Choose a fruit',
  searchPlaceholder: 'Search fruits',
  loadingLabel: 'Loading fruits',
  emptyLabel: 'No fruits found',
  noResultsLabel: 'No matching fruits',
}

describe('LazySearchCombobox', () => {
  it('loads options when opened and selects an item in one interaction flow', async () => {
    const user = userEvent.setup()
    const onLoad = vi.fn()
    const onChange = vi.fn()

    render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={onLoad}
        onChange={onChange}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    await user.click(screen.getByRole('button', { name: /choose a fruit/i }))

    expect(onLoad).toHaveBeenCalledTimes(1)
    expect(screen.getByPlaceholderText(/search fruits/i)).toBeInTheDocument()

    await user.click(screen.getByRole('option', { name: 'Banana' }))

    expect(onChange).toHaveBeenCalledWith(fruits[1])
  })

  it('filters options with the search field', async () => {
    const user = userEvent.setup()

    render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={vi.fn()}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    await user.click(screen.getByRole('button', { name: /choose a fruit/i }))
    await user.type(screen.getByPlaceholderText(/search fruits/i), 'ban')

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Banana' })).toBeInTheDocument()
      expect(screen.queryByRole('option', { name: 'Apple' })).not.toBeInTheDocument()
    })
  })

  it('hides options while a refresh is in progress', async () => {
    const user = userEvent.setup()

    const { rerender } = render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={vi.fn()}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    await user.click(screen.getByRole('button', { name: /choose a fruit/i }))
    expect(screen.getByRole('option', { name: 'Apple' })).toBeInTheDocument()

    rerender(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={[]}
        loading
        loaded
        onLoad={vi.fn()}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    expect(screen.queryByRole('option', { name: 'Apple' })).not.toBeInTheDocument()
    expect(screen.queryByPlaceholderText(/search fruits/i)).not.toBeInTheDocument()
    expect(screen.getByText(/loading fruits/i)).toBeInTheDocument()
  })

  it('shows the search field only after options have loaded', async () => {
    const user = userEvent.setup()

    const { rerender } = render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={[]}
        loading
        loaded={false}
        onLoad={vi.fn()}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    await user.click(screen.getByRole('button', { name: /choose a fruit/i }))

    expect(screen.getByText(/loading fruits/i)).toBeInTheDocument()
    expect(screen.queryByPlaceholderText(/search fruits/i)).not.toBeInTheDocument()

    rerender(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={vi.fn()}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    expect(screen.getByPlaceholderText(/search fruits/i)).toBeInTheDocument()
  })

  it('does not reload when the dropdown is closed with a second click', async () => {
    const user = userEvent.setup()
    const onLoad = vi.fn()

    render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={onLoad}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    const trigger = screen.getByRole('button', { name: /choose a fruit/i })

    await user.click(trigger)
    expect(onLoad).toHaveBeenCalledTimes(1)

    await user.click(trigger)
    expect(onLoad).toHaveBeenCalledTimes(1)
  })

  it('notifies when the panel closes', async () => {
    const user = userEvent.setup()
    const onClose = vi.fn()

    render(
      <LazySearchCombobox
        {...labels}
        value={null}
        options={fruits}
        loading={false}
        loaded
        onLoad={vi.fn()}
        onClose={onClose}
        onChange={vi.fn()}
        getOptionValue={(fruit) => fruit.id}
        getOptionLabel={(fruit) => fruit.name}
      />,
    )

    await user.click(screen.getByRole('button', { name: /choose a fruit/i }))
    await user.click(screen.getByText('Fruit'))

    expect(onClose).toHaveBeenCalledTimes(1)
  })
})
