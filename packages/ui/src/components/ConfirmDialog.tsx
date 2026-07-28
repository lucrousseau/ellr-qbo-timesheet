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
  dangerButtonClass,
  dialogActionsClass,
  dialogBackdropClass,
  dialogContainerClass,
  dialogDescriptionClass,
  dialogPanelClass,
  dialogTitleClass,
} from '../styles/dialogTokens'
import { secondaryButtonClass } from '../styles/tokens'
import { useGuardedAction } from '../hooks/useGuardedAction'

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
            <button
              type="button"
              disabled={isBusy}
              className={`${secondaryButtonClass} px-4 py-2.5 disabled:opacity-50`}
              onClick={onCancel}
            >
              {cancelLabel}
            </button>
            <button
              type="button"
              disabled={isBusy}
              className={dangerButtonClass}
              onClick={() => {
                void handleConfirm()
              }}
            >
              {confirmLabel}
            </button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  )
}
