/**
 * @file Timer display with play/pause control for the timesheet app.
 */

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from 'react'
import { Button, createDurationHoursMinutesMask, MaskedInput, useLocale } from '@ellr/ui'
import { formatElapsedInput, formatElapsedSeconds, parseElapsedInput } from '../utils/timerFormat'

type TimerDisplayProps = {
  elapsedSeconds: number
  maxAccumulatedSeconds: number
  isRunning: boolean
  onToggle: () => void | Promise<void>
  onElapsedCommit: (seconds: number) => void | Promise<boolean>
}

/**
 * Imperative handle for flushing a pending timer edit from the parent panel.
 */
export type TimerDisplayHandle = {
  /**
   * Flushes a pending manual edit and returns parsed seconds when the value changed.
   * @returns Parsed seconds to sync, or null when there is nothing to commit.
   */
  commitPendingEdit: () => number | null
}

const timerTypographyClass = 'font-mono text-4xl font-semibold tracking-tight text-brand-primary'
const timerSecondsClass = 'text-xl font-semibold text-brand-muted'

const timerInputClass = `rounded-lg border border-transparent bg-transparent px-2 py-1 text-center outline-none transition ${timerTypographyClass} focus:border-brand-accent`

/**
 * Large HH:MM:SS timer with click-to-edit input and a play/pause toggle button.
 * @param props Elapsed time, running state, and event handlers.
 * @returns Timer row with digital clock styling.
 */
export const TimerDisplay = forwardRef<TimerDisplayHandle, TimerDisplayProps>(function TimerDisplay(
  { elapsedSeconds, maxAccumulatedSeconds, isRunning, onToggle, onElapsedCommit },
  ref,
) {
  const { t } = useLocale()
  const inputRef = useRef<HTMLInputElement>(null)
  const editBaselineRef = useRef('')
  const draftRef = useRef(formatElapsedInput(elapsedSeconds))
  const skipBlurCommitRef = useRef(false)
  const [draft, setDraft] = useState(() => formatElapsedInput(elapsedSeconds))
  const [isEditing, setIsEditing] = useState(false)
  const { hours, minutes, seconds } = formatElapsedSeconds(elapsedSeconds)
  const durationMask = useMemo(
    () => createDurationHoursMinutesMask(maxAccumulatedSeconds),
    [maxAccumulatedSeconds],
  )

  useEffect(() => {
    if (!isEditing) {
      const formatted = formatElapsedInput(elapsedSeconds)
      draftRef.current = formatted
      setDraft(formatted)
    }
  }, [elapsedSeconds, isEditing])

  const onDraftAccept = (value: string) => {
    draftRef.current = value
    setDraft(value)
  }

  const startEditing = () => {
    const baseline = formatElapsedInput(elapsedSeconds)
    editBaselineRef.current = baseline
    draftRef.current = baseline
    setDraft(baseline)
    setIsEditing(true)
    window.requestAnimationFrame(() => {
      inputRef.current?.focus()
      inputRef.current?.select()
    })
  }

  const flushPendingEdit = (): number | null => {
    const nextDraft = inputRef.current?.value ?? draftRef.current

    if (nextDraft === editBaselineRef.current) {
      return null
    }

    const parsed = parseElapsedInput(nextDraft, maxAccumulatedSeconds)

    if (parsed === null) {
      const formatted = formatElapsedInput(elapsedSeconds)
      draftRef.current = formatted
      setDraft(formatted)

      return null
    }

    if (parsed === elapsedSeconds) {
      return null
    }

    return parsed
  }

  const commitDraft = () => {
    if (skipBlurCommitRef.current) {
      return
    }

    const pendingSeconds = flushPendingEdit()
    setIsEditing(false)

    if (pendingSeconds !== null) {
      void onElapsedCommit(pendingSeconds)
    }
  }

  useImperativeHandle(ref, () => ({
    commitPendingEdit: () => {
      if (!isEditing) {
        return null
      }

      skipBlurCommitRef.current = true
      const pendingSeconds = flushPendingEdit()
      setIsEditing(false)
      skipBlurCommitRef.current = false

      return pendingSeconds
    },
  }))

  const handleToggle = async () => {
    if (isEditing) {
      skipBlurCommitRef.current = true
      const pendingSeconds = flushPendingEdit()
      setIsEditing(false)

      if (pendingSeconds !== null) {
        await onElapsedCommit(pendingSeconds)
      }

      skipBlurCommitRef.current = false
    }

    await onToggle()
  }

  const onInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault()
      inputRef.current?.blur()

      return
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      skipBlurCommitRef.current = true
      const formatted = formatElapsedInput(elapsedSeconds)
      setDraft(formatted)
      draftRef.current = formatted
      setIsEditing(false)
      skipBlurCommitRef.current = false
    }
  }

  return (
    <div className="flex items-center justify-between gap-4">
      <div>
        {isEditing ? (
          <MaskedInput
            mask={durationMask}
            inputRef={inputRef}
            inputMode="numeric"
            autoComplete="off"
            aria-label={t('timesheet.editTimer')}
            className={timerInputClass}
            placeholder="00:00"
            value={draft}
            onAccept={onDraftAccept}
            onBlur={commitDraft}
            onKeyDown={onInputKeyDown}
          />
        ) : (
          <button
            type="button"
            className="rounded-lg border border-transparent px-2 py-1 text-left transition hover:border-brand-border focus:border-brand-accent focus:outline-none"
            aria-label={t('timesheet.editTimer')}
            onClick={startEditing}
          >
            <span className={timerTypographyClass}>
              {hours}
              <span className="text-brand-muted-subtle">:</span>
              {minutes}
              <span className={`text-brand-muted-subtle ${timerSecondsClass}`}>:</span>
              <span className={timerSecondsClass}>{seconds}</span>
            </span>
          </button>
        )}
        <p className="mt-1 text-xs font-medium uppercase tracking-widest text-brand-muted-subtle">
          {isEditing ? t('timesheet.timerInputLabels') : t('timesheet.timerLabels')}
        </p>
      </div>
      <Button type="button" variant="secondary" onClick={() => void handleToggle()}>
        {isRunning ? t('timesheet.pause') : t('timesheet.play')}
      </Button>
    </div>
  )
})
