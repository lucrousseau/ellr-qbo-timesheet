import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ComponentProps } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS } from '@ellr/api-client'
import { LocaleProvider } from '@ellr/ui'
import { TimerDisplay } from './TimerDisplay'

function renderTimerDisplay(props: Partial<ComponentProps<typeof TimerDisplay>> = {}) {
  return render(
    <LocaleProvider>
      <TimerDisplay
        elapsedSeconds={125}
        maxAccumulatedSeconds={DEFAULT_TIME_TRACKER_MAX_ACCUMULATED_SECONDS}
        isRunning={false}
        onToggle={vi.fn()}
        onElapsedCommit={vi.fn().mockResolvedValue(undefined)}
        {...props}
      />
    </LocaleProvider>,
  )
}

describe('TimerDisplay', () => {
  it('enters edit mode when the elapsed time is clicked', () => {
    renderTimerDisplay()

    fireEvent.click(screen.getByRole('button', { name: 'Edit elapsed time' }))

    expect(screen.getByLabelText('Edit elapsed time')).toHaveValue('00:02')
  })

  it('commits a manual edit before starting the timer', async () => {
    const user = userEvent.setup()
    const onToggle = vi.fn()
    const onElapsedCommit = vi.fn().mockResolvedValue(undefined)
    renderTimerDisplay({ onToggle, onElapsedCommit })

    await user.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    await waitFor(() => expect(input).toHaveFocus())
    await user.tripleClick(input)
    await user.keyboard('1234')
    await user.click(screen.getByRole('button', { name: 'Start timer' }))

    await waitFor(() => {
      expect(onElapsedCommit).toHaveBeenCalledWith(12 * 3600 + 34 * 60)
    })
    expect(onToggle).toHaveBeenCalled()
  })

  it('formats digit input as HH:MM and ignores letters', async () => {
    const user = userEvent.setup()
    const onElapsedCommit = vi.fn().mockResolvedValue(undefined)
    renderTimerDisplay({ onElapsedCommit })

    await user.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    await waitFor(() => expect(input).toHaveFocus())
    await user.tripleClick(input)
    await user.keyboard('ab12cd34')
    await user.keyboard('{Enter}')

    await waitFor(() => {
      expect(onElapsedCommit).toHaveBeenCalledWith(12 * 3600 + 34 * 60)
    })
  })

  it('keeps elapsed seconds when edit mode is closed without changes', () => {
    const onElapsedCommit = vi.fn().mockResolvedValue(undefined)
    renderTimerDisplay({ elapsedSeconds: 125, onElapsedCommit })

    fireEvent.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    fireEvent.blur(screen.getByLabelText('Edit elapsed time'))

    expect(onElapsedCommit).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Edit elapsed time' })).toHaveTextContent('00:02:05')
  })

  it('commits edited duration when Enter is pressed', async () => {
    const user = userEvent.setup()
    const onElapsedCommit = vi.fn().mockResolvedValue(undefined)
    renderTimerDisplay({ onElapsedCommit })

    await user.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    await user.type(input, '0010', { initialSelectionStart: 0, initialSelectionEnd: 5 })
    await user.keyboard('{Enter}')

    await waitFor(() => {
      expect(onElapsedCommit).toHaveBeenCalledWith(600)
    })
    expect(screen.getByRole('button', { name: 'Edit elapsed time' })).toBeInTheDocument()
  })

  it('cancels edit and restores display when Escape is pressed', async () => {
    const user = userEvent.setup()
    const onElapsedCommit = vi.fn().mockResolvedValue(undefined)
    renderTimerDisplay({ elapsedSeconds: 125, onElapsedCommit })

    await user.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    await user.type(input, '0010', { initialSelectionStart: 0, initialSelectionEnd: 5 })
    await user.keyboard('{Escape}')

    expect(onElapsedCommit).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Edit elapsed time' })).toHaveTextContent('00:02:05')
  })
})
