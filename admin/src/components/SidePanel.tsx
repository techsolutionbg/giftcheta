import { useEffect, useId, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import * as React from 'react'
import './SidePanel.css'
import { X } from 'lucide-react'

export type SidePanelSide = 'top' | 'right' | 'bottom' | 'left'

type SidePanelProps = {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  side?: SidePanelSide
  title: React.ReactNode
  description?: React.ReactNode
  children: React.ReactNode
  trigger?: React.ReactElement<{ onClick?: React.MouseEventHandler }>
  className?: string
  panelClassName?: string
  closeOnOverlayClick?: boolean
  closeOnEscape?: boolean
  labelledBy?: string
}

/** Responsive, accessible slide-in panel for drawers, inspectors and mobile navigation. */
export function SidePanel({
  open: controlledOpen,
  onOpenChange,
  side = 'right',
  title,
  description,
  children,
  trigger,
  className = '',
  panelClassName = '',
  closeOnOverlayClick = true,
  closeOnEscape = true,
  labelledBy,
}: SidePanelProps) {
  const id = useId()
  const titleId = labelledBy ?? `${id}-title`
  const panelRef = useRef<HTMLElement>(null)
  const triggerRef = useRef<HTMLElement | null>(null)
  const [internalOpen, setInternalOpen] = useState(false)
  const isControlled = controlledOpen !== undefined
  const [rendered, setRendered] = useState(isControlled ? controlledOpen : false)
  const [closing, setClosing] = useState(false)
  const isOpen = isControlled ? controlledOpen : internalOpen

  const setOpen = (next: boolean) => {
    if (!isControlled) setInternalOpen(next)
    onOpenChange?.(next)
  }

  useEffect(() => {
    if (isOpen) {
      setRendered(true)
      setClosing(false)
      return
    }
    if (!rendered) return
    setClosing(true)
    const timeout = window.setTimeout(() => {
      setRendered(false)
      setClosing(false)
      triggerRef.current?.focus()
    }, 280)
    return () => window.clearTimeout(timeout)
  }, [isOpen])

  useEffect(() => {
    if (!isOpen) return
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const closeOnKey = (event: KeyboardEvent) => {
      if (closeOnEscape && event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('keydown', closeOnKey)
    panelRef.current?.focus()
    return () => {
      document.body.style.overflow = previousOverflow
      document.removeEventListener('keydown', closeOnKey)
    }
  }, [isOpen, closeOnEscape])

  const renderedTrigger = trigger ? React.cloneElement(trigger, {
    ref: (node: HTMLElement | null) => {
      triggerRef.current = node
      const originalRef = (trigger as React.ReactElement & { ref?: React.Ref<HTMLElement> }).ref
      if (typeof originalRef === 'function') originalRef(node)
      else if (originalRef && typeof originalRef === 'object') (originalRef as React.MutableRefObject<HTMLElement | null>).current = node
    },
    'aria-haspopup': 'dialog',
    'aria-expanded': isOpen,
    onClick: (event: React.MouseEvent) => {
      trigger.props.onClick?.(event)
      if (!event.defaultPrevented) setOpen(true)
    },
  } as React.HTMLAttributes<HTMLElement>) : null

  return <>
    {renderedTrigger}
    {rendered && createPortal(
      <div className={`side-panel-layer ${className}`.trim()} data-side={side} data-state={closing ? 'closed' : 'open'}>
        <button
          type="button"
          className="side-panel-overlay"
          aria-label="Затвори панела"
          tabIndex={closeOnOverlayClick ? 0 : -1}
          onClick={() => closeOnOverlayClick && setOpen(false)}
        />
        <section
          ref={panelRef}
          className={`side-panel ${panelClassName}`.trim()}
          role="dialog"
          aria-modal="true"
          aria-hidden={!isOpen}
          aria-labelledby={titleId}
          tabIndex={-1}
          data-state={closing ? 'closed' : 'open'}
        >
          <header className="side-panel-header">
            <div className="side-panel-heading">
              <h2 id={titleId}>{title}</h2>
              {description && <p>{description}</p>}
            </div>
            <button type="button" className="icon-button" title="Затвори панела" aria-label="Затвори панела" onClick={() => setOpen(false)}><X size={18}/></button>
          </header>
          <div className="side-panel-content">{children}</div>
        </section>
      </div>,
      document.body,
    )}
  </>
}
