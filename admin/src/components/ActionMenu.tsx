import { useEffect, useRef, useState } from 'react'
import type * as React from 'react'
import { createPortal } from 'react-dom'
import type { LucideIcon } from 'lucide-react'
import { MoreVertical } from 'lucide-react'

export type ActionMenuItem = {
  label: string
  onSelect: () => void
  icon?: LucideIcon
  danger?: boolean
  disabled?: boolean
}

type ActionMenuProps = {
  label: string
  items: ActionMenuItem[]
}

export function ActionMenu({ label, items }: ActionMenuProps) {
  const rootRef = useRef<HTMLDivElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const [open, setOpen] = useState(false)
  const [position, setPosition] = useState({ top: 0, left: 0 })

  useEffect(() => {
    if (!open) return
    const placeMenu = () => {
      const bounds = triggerRef.current?.getBoundingClientRect()
      if (!bounds) return
      const menuHeight = menuRef.current?.getBoundingClientRect().height ?? 120
      const top = bounds.bottom + menuHeight + 8 > window.innerHeight ? bounds.top - menuHeight - 6 : bounds.bottom + 6
      setPosition({ top: Math.max(8, top), left: Math.min(window.innerWidth - 200, Math.max(8, bounds.right - 190)) })
    }
    const closeOutside = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node) && !menuRef.current?.contains(event.target as Node)) setOpen(false)
    }
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
        triggerRef.current?.focus()
      }
    }
    placeMenu()
    window.addEventListener('resize', placeMenu)
    window.addEventListener('scroll', placeMenu, true)
    document.addEventListener('pointerdown', closeOutside)
    document.addEventListener('keydown', closeOnEscape)
    return () => {
      window.removeEventListener('resize', placeMenu)
      window.removeEventListener('scroll', placeMenu, true)
      document.removeEventListener('pointerdown', closeOutside)
      document.removeEventListener('keydown', closeOnEscape)
    }
  }, [open])

  const handleMenuKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return
    event.preventDefault()
    const enabled = Array.from(menuRef.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') ?? [])
    const current = enabled.indexOf(document.activeElement as HTMLButtonElement)
    const next = event.key === 'ArrowDown' ? (current + 1) % enabled.length : (current - 1 + enabled.length) % enabled.length
    enabled[next]?.focus()
  }

  return <div className="action-menu-root" ref={rootRef}>
    <button ref={triggerRef} type="button" className="action-menu-trigger" title={label} aria-label={label} aria-haspopup="menu" aria-expanded={open} onClick={() => setOpen(value => !value)}>
      <MoreVertical size={17}/>
    </button>
    {open && createPortal(<div ref={menuRef} className="action-menu-popover" role="menu" style={{ top: position.top, left: position.left }} onKeyDown={handleMenuKeyDown}>
      {items.map((item, index) => {
        const Icon = item.icon
        return <button key={`${item.label}-${index}`} type="button" role="menuitem" disabled={item.disabled} className={`action-menu-item${item.danger ? ' danger' : ''}`} onClick={() => { setOpen(false); item.onSelect() }}>
          {Icon && <Icon size={15}/>}<span>{item.label}</span>
        </button>
      })}
    </div>, document.body)}
  </div>
}
