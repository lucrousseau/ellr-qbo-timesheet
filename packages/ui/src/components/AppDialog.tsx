/**
 * @file Generic modal dialog with title, body, and optional footer actions.
 */

import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import {
  dialogBackdropClass,
  dialogContainerClass,
  dialogPanelClass,
  dialogTitleClass,
} from '../styles/dialogTokens'

type AppDialogProps = {
  open: boolean
  title: string
  onClose: () => void
  children: React.ReactNode
  footer?: React.ReactNode
  panelClassName?: string
}

/**
 * Accessible modal shell for non-destructive forms and detail panels.
 * @param props Open state, title, body content, and optional footer actions.
 * @returns Modal dialog UI.
 */
export function AppDialog({
  open,
  title,
  onClose,
  children,
  footer,
  panelClassName = dialogPanelClass,
}: AppDialogProps) {
  return (
    <Dialog open={open} onClose={onClose}>
      <DialogBackdrop className={dialogBackdropClass} />
      <div className={dialogContainerClass}>
        <DialogPanel className={panelClassName}>
          <DialogTitle className={dialogTitleClass}>{title}</DialogTitle>
          <div className="mt-4">{children}</div>
          {footer ? <div className="mt-6 flex justify-end gap-3">{footer}</div> : null}
        </DialogPanel>
      </div>
    </Dialog>
  )
}
