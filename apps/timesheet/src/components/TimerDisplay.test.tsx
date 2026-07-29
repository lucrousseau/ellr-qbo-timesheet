import { fireEvent, render, screen } from '@testing-library/react'
import type { ComponentProps } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { LocaleProvider } from '@ellr/ui'
import { TimerDisplay } from './TimerDisplay'

function renderTimerDisplay(props: Partial<ComponentProps<typeof TimerDisplay>> = {}) {
  return render(
    <LocaleProvider>
      <TimerDisplay
        elapsedSeconds={125}
        isRunning={false}
        onToggle={vi.fn()}
        onElapsedChange={vi.fn()}
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

  it('commits a valid edited duration on blur', () => {
    const onElapsedChange = vi.fn()
    renderTimerDisplay({ onElapsedChange })

    fireEvent.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    fireEvent.change(input, { target: { value: '00:10' } })
    fireEvent.blur(input)

    expect(onElapsedChange).toHaveBeenCalledWith(600)
  })

  it('reverts invalid input without calling onElapsedChange', () => {
    const onElapsedChange = vi.fn()
    renderTimerDisplay({ onElapsedChange })

    fireEvent.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    const input = screen.getByLabelText('Edit elapsed time')
    fireEvent.change(input, { target: { value: 'invalid' } })
    fireEvent.blur(input)

    expect(onElapsedChange).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Edit elapsed time' })).toHaveTextContent('00:02:05')
  })

  it('keeps elapsed seconds when edit mode is closed without changes', () => {
    const onElapsedChange = vi.fn()
    renderTimerDisplay({ elapsedSeconds: 125, onElapsedChange })

    fireEvent.click(screen.getByRole('button', { name: 'Edit elapsed time' }))
    fireEvent.blur(screen.getByLabelText('Edit elapsed time'))

    expect(onElapsedChange).not.toHaveBeenCalled()
    expect(screen.getByRole('button', { name: 'Edit elapsed time' })).toHaveTextContent('00:02:05')
  })
})
