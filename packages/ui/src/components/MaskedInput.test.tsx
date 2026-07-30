/**
 * @file Tests for MaskedInput.
 */

import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { durationHoursMinutesMask } from '../masks/durationMask'
import { MaskedInput } from './MaskedInput'

/**
 * Stateful fixture for exercising controlled MaskedInput behavior in tests.
 */
function DurationInputFixture({
  initialValue = '00:00',
  onAccept = vi.fn(),
}: {
  initialValue?: string
  onAccept?: (value: string) => void
}) {
  const [value, setValue] = useState(initialValue)

  return (
    <MaskedInput
      mask={durationHoursMinutesMask}
      value={value}
      aria-label="Duration"
      onAccept={(nextValue) => {
        setValue(nextValue)
        onAccept(nextValue)
      }}
    />
  )
}

describe('MaskedInput', () => {
  it('formats duration input as HH:MM while typing', async () => {
    const user = userEvent.setup()
    const onAccept = vi.fn()

    render(<DurationInputFixture onAccept={onAccept} />)

    const input = screen.getByLabelText('Duration')
    await user.type(input, '1234', { initialSelectionStart: 0, initialSelectionEnd: 5 })

    expect(onAccept).toHaveBeenLastCalledWith('12:34')
    expect(input).toHaveValue('12:34')
  })

  it('ignores letters in a duration mask', async () => {
    const user = userEvent.setup()
    const onAccept = vi.fn()

    render(<DurationInputFixture onAccept={onAccept} />)

    const input = screen.getByLabelText('Duration')
    await user.type(input, 'ab12cd34', { initialSelectionStart: 0, initialSelectionEnd: 5 })

    expect(onAccept).toHaveBeenLastCalledWith('12:34')
    expect(input).toHaveValue('12:34')
  })

  it('caps minutes at 59 while typing', async () => {
    const user = userEvent.setup()
    const onAccept = vi.fn()

    render(<DurationInputFixture onAccept={onAccept} />)

    const input = screen.getByLabelText('Duration')
    await user.type(input, '0199', { initialSelectionStart: 0, initialSelectionEnd: 5 })

    expect(onAccept).toHaveBeenLastCalledWith('01:59')
    expect(input).toHaveValue('01:59')
  })
})
