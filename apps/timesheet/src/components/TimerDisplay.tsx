/**
 * @file Timer display with play/pause control for the timesheet app.
 */

import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { Button, useLocale } from '@ellr/ui'
import { formatElapsedInput, formatElapsedSeconds, parseElapsedInput } from '../utils/timerFormat'

type TimerDisplayProps = {
  elapsedSeconds: number
  isRunning: boolean
  onToggle: () => void
  onElapsedChange: (seconds: number) => void
}

const timerTypographyClass = 'font-mono text-4xl font-semibold tracking-tight text-slate-900'
const timerSecondsClass = 'text-xl font-semibold text-slate-600'

const timerInputClass = `rounded-lg border border-transparent bg-transparent px-2 py-1 text-center outline-none transition ${timerTypographyClass} focus:border-blue-500`

/**
 * Large HH:MM:SS timer with click-to-edit input and a play/pause toggle button.
 * @param props Elapsed time, running state, and event handlers.
 * @returns Timer row with digital clock styling.
 */
export function TimerDisplay({
  elapsedSeconds,
  isRunning,
  onToggle,
  onElapsedChange,
}: TimerDisplayProps) {
  const { t } = useLocale()
  const inputRef = useRef<HTMLInputElement>(null)
  const editBaselineRef = useRef('')
  const [draft, setDraft] = useState(() => formatElapsedInput(elapsedSeconds))
  const [isEditing, setIsEditing] = useState(false)
  const { hours, minutes, seconds } = formatElapsedSeconds(elapsedSeconds)

  useEffect(() => {
    if (!isEditing) {
      setDraft(formatElapsedInput(elapsedSeconds))
    }
  }, [elapsedSeconds, isEditing])

  const startEditing = () => {
    const baseline = formatElapsedInput(elapsedSeconds)
    editBaselineRef.current = baseline
    setDraft(baseline)
    setIsEditing(true)
    window.requestAnimationFrame(() => {
      inputRef.current?.focus()
      inputRef.current?.select()
    })
  }

  const commitDraft = () => {
    setIsEditing(false)

    if (draft === editBaselineRef.current) {
      return
    }

    const parsed = parseElapsedInput(draft)

    if (parsed === null) {
      setDraft(formatElapsedInput(elapsedSeconds))

      return
    }

    if (parsed !== elapsedSeconds) {
      onElapsedChange(parsed)
    }
  }

  const onInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault()
      inputRef.current?.blur()
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      setDraft(formatElapsedInput(elapsedSeconds))
      setIsEditing(false)
      inputRef.current?.blur()
    }
  }

  return (
    <div className="flex items-center justify-between gap-4">
      <div>
        {isEditing ? (
          <input
            ref={inputRef}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            aria-label={t('timesheet.editTimer')}
            className={timerInputClass}
            placeholder="00:00"
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            onBlur={commitDraft}
            onKeyDown={onInputKeyDown}
          />
        ) : (
          <button
            type="button"
            className="rounded-lg border border-transparent px-2 py-1 text-left transition hover:border-slate-200 focus:border-blue-500 focus:outline-none"
            aria-label={t('timesheet.editTimer')}
            onClick={startEditing}
          >
            <span className={timerTypographyClass}>
              {hours}
              <span className="text-slate-400">:</span>
              {minutes}
              <span className={`text-slate-400 ${timerSecondsClass}`}>:</span>
              <span className={timerSecondsClass}>{seconds}</span>
            </span>
          </button>
        )}
        <p className="mt-1 text-xs font-medium uppercase tracking-widest text-slate-400">
          {isEditing ? t('timesheet.timerInputLabels') : t('timesheet.timerLabels')}
        </p>
      </div>
      <Button type="button" variant="secondary" onClick={onToggle}>
        {isRunning ? t('timesheet.pause') : t('timesheet.play')}
      </Button>
    </div>
  )
}
