/**
 * @file Open eye icon for password visibility toggle.
 */

type EyeIconProps = {
  className?: string
}

/**
 * Eye icon indicating password is currently hidden (click to show).
 * @param props Optional Tailwind class override.
 * @returns SVG eye.
 */
export function EyeIcon({ className = 'h-4 w-4' }: EyeIconProps) {
  return (
    <svg aria-hidden="true" className={className} viewBox="0 0 20 20" fill="currentColor">
      <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
      <path
        fillRule="evenodd"
        d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"
        clipRule="evenodd"
      />
    </svg>
  )
}
