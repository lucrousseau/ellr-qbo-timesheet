/**
 * @file Dialog for assigning a supervisor to a provisioned timesheet user.
 */

import { AppDialog, Button, StaticSelect, useLocale } from '@ellr/ui'
import { useEffect, useMemo, useState } from 'react'
import type { User } from '../hooks/useTimesheetProvisioning'

type SupervisorOption = {
  value: string
  label: string
}

type AssignSupervisorDialogProps = {
  user: User | null
  supervisorOptions: User[]
  saving: boolean
  onClose: () => void
  onSave: (userId: number, supervisorId: number | null) => Promise<void>
}

/**
 * Assigns or clears the supervisor for a provisioned timesheet user.
 * @param props Target user, supervisor options, and save handlers.
 * @returns Supervisor assignment dialog markup.
 */
export function AssignSupervisorDialog({
  user,
  supervisorOptions,
  saving,
  onClose,
  onSave,
}: AssignSupervisorDialogProps) {
  const { t } = useLocale()
  const [selectedOption, setSelectedOption] = useState<SupervisorOption | null>(null)

  const options = useMemo<SupervisorOption[]>(
    () => [
      { value: '', label: t('admin.noSupervisorAssigned') },
      ...supervisorOptions
        .filter((candidate) => candidate.id !== user?.id)
        .map((candidate) => ({
          value: String(candidate.id),
          label: candidate.name,
        })),
    ],
    [supervisorOptions, t, user?.id],
  )

  useEffect(() => {
    if (!user) {
      setSelectedOption(null)
      return
    }

    const current = options.find((option) => option.value === String(user.supervisor_id ?? '')) ?? options[0]
    setSelectedOption(current)
  }, [options, user])

  if (!user || selectedOption === null) {
    return null
  }

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    const supervisorId = selectedOption.value === '' ? null : Number(selectedOption.value)
    await onSave(user.id, supervisorId)
    onClose()
  }

  return (
    <AppDialog
      open={user !== null}
      title={t('admin.assignSupervisorTitle', { name: user.name })}
      onClose={onClose}
    >
      <form className="space-y-4" onSubmit={(event) => void handleSubmit(event)}>
        <p className="text-sm text-slate-600">{t('admin.assignSupervisorHelp')}</p>

        <StaticSelect
          label={t('admin.supervisorLabel')}
          value={selectedOption}
          options={options}
          onChange={setSelectedOption}
          getOptionValue={(option) => option.value}
          getOptionLabel={(option) => option.label}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose} disabled={saving}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" variant="primary" disabled={saving}>
            {saving ? t('admin.savingSupervisor') : t('common.save')}
          </Button>
        </div>
      </form>
    </AppDialog>
  )
}
