/**
 * @file Pencil icon for edit and detail actions.
 */

type PencilIconProps = {
  className?: string
}

/**
 * Pencil glyph for compact edit actions.
 * @param props Optional SVG className.
 * @returns SVG pencil icon.
 */
export function PencilIcon({ className = 'h-4 w-4' }: PencilIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path d="M15.586 3.586a2 2 0 0 1 0 2.828l-.793.793-2.828-2.828.793-.793a2 2 0 0 1 2.828 0Z" />
      <path d="M11.257 4.672 4.5 11.43V14.5h3.07l6.757-6.757-2.07-2.071Z" />
    </svg>
  )
}
