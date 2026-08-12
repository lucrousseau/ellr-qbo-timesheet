/**
 * @file Three linked hub nodes used inside the ellr pastille mark.
 */

const HUB_POSITIONS = [4.5, 12, 19.5] as const
const HUB_SIDE_RADIUS = 2.75
const HUB_CENTER_RADIUS = 3.75
const HUB_STROKE_WIDTH = 1.65
const HUB_CENTER_Y = 12

type EllrLogoGraphicProps = {
  className?: string
  width?: number
  height?: number
  /** Node and connector color (defaults to on-accent cream). */
  color?: string
}

/**
 * Hub-linked mark drawn in a 24×24 viewBox (matches website-ellr media kit).
 * @param props Optional size and stroke/fill color.
 * @returns Decorative SVG graphic.
 */
export function EllrLogoGraphic({
  className,
  width,
  height,
  color = '#EAF1F0',
}: EllrLogoGraphicProps) {
  const [leftX, centerX, rightX] = HUB_POSITIONS

  const segments = [
    { x1: leftX + HUB_SIDE_RADIUS, x2: centerX - HUB_CENTER_RADIUS },
    { x1: centerX + HUB_CENTER_RADIUS, x2: rightX - HUB_SIDE_RADIUS },
  ]

  return (
    <svg
      viewBox="0 0 24 24"
      width={width}
      height={height}
      fill="none"
      className={className}
      aria-hidden
    >
      {segments.map(({ x1, x2 }, index) => (
        <line
          key={index}
          x1={x1}
          y1={HUB_CENTER_Y}
          x2={x2}
          y2={HUB_CENTER_Y}
          stroke={color}
          strokeWidth={HUB_STROKE_WIDTH}
          strokeLinecap="round"
        />
      ))}
      <circle cx={leftX} cy={HUB_CENTER_Y} r={HUB_SIDE_RADIUS} fill={color} />
      <circle cx={centerX} cy={HUB_CENTER_Y} r={HUB_CENTER_RADIUS} fill={color} />
      <circle cx={rightX} cy={HUB_CENTER_Y} r={HUB_SIDE_RADIUS} fill={color} />
    </svg>
  )
}
