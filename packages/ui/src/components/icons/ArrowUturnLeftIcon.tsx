/**
 * @file U-turn arrow icon for return-to-draft actions.
 */

type ArrowUturnLeftIconProps = {
  className?: string
}

/**
 * U-turn arrow glyph for compact return-to-draft actions.
 * @param props Optional SVG className.
 * @returns SVG arrow icon.
 */
export function ArrowUturnLeftIcon({ className = 'h-4 w-4' }: ArrowUturnLeftIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M7.78 3.22a.75.75 0 0 1 0 1.06L5.06 7H11a5 5 0 0 1 0 10h-1a.75.75 0 0 1 0-1.5h1a3.5 3.5 0 1 0 0-7H5.06l2.72 2.72a.75.75 0 1 1-1.06 1.06l-4-4a.75.75 0 0 1 0-1.06l4-4a.75.75 0 0 1 1.06 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}
