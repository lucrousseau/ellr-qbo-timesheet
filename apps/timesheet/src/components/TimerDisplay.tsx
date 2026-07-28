/**
 * @file Timer display with play/pause control for the timesheet app.
 */

import { Button, useLocale } from '@ellr/ui'
import { formatElapsedSeconds } from '../utils/timerFormat'

type TimerDisplayProps = {
  elapsedSeconds: number
  isRunning: boolean
  onToggle: () => void
}

/**
 * Large HH:MM:SS timer with a play/pause toggle button.
 * @param props Elapsed time, running state, and toggle handler.
 * @returns Timer row with digital clock styling.
 */
export function TimerDisplay({ elapsedSeconds, isRunning, onToggle }: TimerDisplayProps) {
  const { t } = useLocale()
  const { hours, minutes, seconds } = formatElapsedSeconds(elapsedSeconds)

  return (
    <div className="flex items-center justify-between gap-4">
      <div>
        <p className="font-mono text-4xl font-semibold tracking-tight text-slate-900">
          {hours}
          <span className="text-slate-400">:</span>
          {minutes}
          <span className="text-slate-400">:</span>
          <span className="text-2xl text-slate-700">{seconds}</span>
        </p>
        <p className="mt-1 text-xs font-medium uppercase tracking-widest text-slate-400">
          {t('timesheet.timerLabels')}
        </p>
      </div>
      <Button type="button" variant="secondary" onClick={onToggle}>
        {isRunning ? t('timesheet.pause') : t('timesheet.play')}
      </Button>
    </div>
  )
}
