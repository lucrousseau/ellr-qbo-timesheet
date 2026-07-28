/**
 * @file Confirmation modal for destructive or irreversible actions.
 */

import {
  Description,
  Dialog,
  DialogBackdrop,
  DialogPanel,
  DialogTitle,
} from '@headlessui/react'
import {
  dialogActionsClass,
  dialogBackdropClass,
  dialogContainerClass,
  dialogDescriptionClass,
  dialogPanelClass,
  dialogTitleClass,
} from '../styles/dialogTokens'
import { useGuardedAction } from '../hooks/useGuardedAction'
import { Button } from './Button'

type ConfirmDialogProps = {
  open: boolean
  title: string
  description: string
  confirmLabel: string
  cancelLabel: string
  confirming?: boolean
  onConfirm: () => void | Promise<void>
  onCancel: () => void
}

/**
 * Two-action confirmation dialog with alertdialog semantics.
 * @param props Open state, copy, and confirm/cancel handlers.
 * @returns Modal confirmation UI.
 */
export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  cancelLabel,
  confirming = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const { run: handleConfirm, pending: confirmingAction } = useGuardedAction(onConfirm)
  const isBusy = confirming || confirmingAction

  return (
    <Dialog open={open} onClose={onCancel} role="alertdialog">
      <DialogBackdrop className={dialogBackdropClass} />
      <div className={dialogContainerClass}>
        <DialogPanel className={dialogPanelClass}>
          <DialogTitle className={dialogTitleClass}>{title}</DialogTitle>
          <Description className={dialogDescriptionClass}>{description}</Description>
          <div className={dialogActionsClass}>
            <Button type="button" variant="secondary" disabled={isBusy} onClick={onCancel}>
              {cancelLabel}
            </Button>
            <Button
              type="button"
              variant="danger"
              disabled={isBusy}
              onClick={() => {
                void handleConfirm()
              }}
            >
              {confirmLabel}
            </Button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  )
}
