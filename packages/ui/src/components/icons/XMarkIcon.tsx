/**
 * @file X-mark icon for compact reject actions.
 */

type XMarkIconProps = {
  className?: string
}

/**
 * X-mark glyph for compact reject actions.
 * @param props Optional SVG className.
 * @returns SVG x-mark icon.
 */
export function XMarkIcon({ className = 'h-4 w-4' }: XMarkIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
    </svg>
  )
}
