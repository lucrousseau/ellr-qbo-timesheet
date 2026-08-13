/**
 * @file Check icon for compact approve actions.
 */

type CheckIconProps = {
  className?: string
}

/**
 * Check glyph for compact approve actions.
 * @param props Optional SVG className.
 * @returns SVG check icon.
 */
export function CheckIcon({ className = 'h-4 w-4' }: CheckIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path
        fillRule="evenodd"
        d="M16.704 5.29a1 1 0 0 1 .006 1.414l-7.25 7.333a1 1 0 0 1-1.432.012L3.29 9.79a1 1 0 1 1 1.42-1.408l3.59 3.622 6.54-6.615a1 1 0 0 1 1.414-.006Z"
        clipRule="evenodd"
      />
    </svg>
  )
}
