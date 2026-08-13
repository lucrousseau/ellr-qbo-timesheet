/**
 * @file Trash icon for destructive compact actions.
 */

type TrashIconProps = {
  className?: string
}

/**
 * Trash glyph for compact delete actions.
 * @param props Optional SVG className.
 * @returns SVG trash icon.
 */
export function TrashIcon({ className = 'h-4 w-4' }: TrashIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M7 2a1 1 0 0 0-.894.553L5.382 4H3a1 1 0 0 0 0 2h.293l.853 9.383A2 2 0 0 0 6.138 17h7.724a2 2 0 0 0 1.992-1.617L16.707 6H17a1 1 0 1 0 0-2h-2.382l-.724-1.447A1 1 0 0 0 13 2H7Zm1.118 4-.618 8h1.01l.618-8H8.118Zm3.764 0-.618 8h1.01l.618-8h-1.01Z"
        clipRule="evenodd"
      />
    </svg>
  )
}
