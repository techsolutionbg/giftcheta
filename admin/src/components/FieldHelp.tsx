import { useCallback, useId, useLayoutEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { CircleHelp } from 'lucide-react'

type Side = 'top' | 'right' | 'bottom' | 'left'
type Position = { side: Side; left: number; top: number }

type FieldHelpProps = { content: string }

export function FieldHelp({ content }: FieldHelpProps) {
  const triggerRef = useRef<HTMLButtonElement>(null)
  const tooltipRef = useRef<HTMLDivElement>(null)
  const hideTimer = useRef<number | undefined>(undefined)
  const [open, setOpen] = useState(false)
  const [position, setPosition] = useState<Position | null>(null)
  const tooltipId = `field-help-${useId()}`

  const clearHideTimer = () => window.clearTimeout(hideTimer.current)
  const hideSoon = () => {
    clearHideTimer()
    hideTimer.current = window.setTimeout(() => setOpen(false), 120)
  }

  const updatePosition = useCallback(() => {
    const anchor = triggerRef.current?.getBoundingClientRect()
    const tooltip = tooltipRef.current
    if (!anchor || !tooltip) return

    const { width, height } = tooltip.getBoundingClientRect()
    const gap = 9
    const margin = 12
    const spaces = {
      top: anchor.top - margin,
      right: window.innerWidth - anchor.right - margin,
      bottom: window.innerHeight - anchor.bottom - margin,
      left: anchor.left - margin,
    }
    const verticalFit = (['top', 'bottom'] as const).filter(side => spaces[side] >= height + gap)
    const horizontalFit = (['right', 'left'] as const).filter(side => spaces[side] >= width + gap)
    const side: Side = verticalFit.length
      ? verticalFit.sort((a, b) => spaces[b] - spaces[a])[0]
      : horizontalFit.length
        ? horizontalFit.sort((a, b) => spaces[b] - spaces[a])[0]
        : (Object.entries(spaces).sort((a, b) => b[1] - a[1])[0][0] as Side)

    const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(value, max))
    let left = anchor.left + anchor.width / 2 - width / 2
    let top = anchor.bottom + gap
    if (side === 'top') top = anchor.top - height - gap
    if (side === 'left') {
      left = anchor.left - width - gap
      top = anchor.top + anchor.height / 2 - height / 2
    }
    if (side === 'right') {
      left = anchor.right + gap
      top = anchor.top + anchor.height / 2 - height / 2
    }
    left = clamp(left, margin, Math.max(margin, window.innerWidth - width - margin))
    top = clamp(top, margin, Math.max(margin, window.innerHeight - height - margin))
    setPosition({ side, left, top })
  }, [])

  useLayoutEffect(() => {
    if (!open) {
      setPosition(null)
      return
    }
    updatePosition()
    window.addEventListener('resize', updatePosition)
    document.addEventListener('scroll', updatePosition, true)
    return () => {
      window.removeEventListener('resize', updatePosition)
      document.removeEventListener('scroll', updatePosition, true)
    }
  }, [open, updatePosition, content])

  return <>
    <button
      ref={triggerRef}
      type="button"
      className="field-help-trigger"
      aria-label={`Помощ за полето: ${content}`}
      aria-describedby={open ? tooltipId : undefined}
      aria-expanded={open}
      onMouseEnter={() => { clearHideTimer(); setOpen(true) }}
      onMouseLeave={hideSoon}
      onFocus={() => setOpen(true)}
      onBlur={() => setOpen(false)}
      onClick={() => setOpen(true)}
      onKeyDown={event => { if (event.key === 'Escape') setOpen(false) }}
    >
      <CircleHelp size={16} aria-hidden="true"/>
    </button>
    {open && createPortal(
      <div
        ref={tooltipRef}
        id={tooltipId}
        role="tooltip"
        className="field-help-tooltip"
        data-placement={position?.side ?? 'bottom'}
        style={position ? { left: position.left, top: position.top } : { left: -10000, top: -10000 }}
        onMouseEnter={clearHideTimer}
        onMouseLeave={hideSoon}
      >
        {content}
      </div>,
      document.body,
    )}
  </>
}
