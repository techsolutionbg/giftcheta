import { useEffect, useId, useMemo, useRef, useState } from 'react'
import type * as React from 'react'
import { Check, ChevronDown } from 'lucide-react'

export type DropdownOption = { value: string; label: string; selectedLabel?: string; searchText?: string; disabled?: boolean }

type DropdownProps = {
  value: string
  options: DropdownOption[]
  onChange: (value: string) => void
  ariaLabel: string
  placeholder?: string
  className?: string
  disabled?: boolean
  ariaLabelledBy?: string
  searchable?: boolean
  searchPlaceholder?: string
}

export function Dropdown({ value, options, onChange, ariaLabel, placeholder = 'Изберете…', className = '', disabled = false, ariaLabelledBy, searchable = false, searchPlaceholder = 'Търсене…' }: DropdownProps) {
  const id = useId()
  const rootRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const searchRef = useRef<HTMLInputElement>(null)
  const optionRefs = useRef<Array<HTMLButtonElement | null>>([])
  const selectedIndex = options.findIndex(option => option.value === value)
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(Math.max(selectedIndex, 0))
  const [placement, setPlacement] = useState<'bottom' | 'top'>('bottom')
  const [search, setSearch] = useState('')
  const selectedOption = selectedIndex >= 0 ? options[selectedIndex] : undefined
  const visible = useMemo(() => options.map((option, index) => ({ option, index })).filter(({ option }) => {
    const query = search.trim().toLocaleLowerCase()
    return !query || option.label.toLocaleLowerCase().includes(query) || option.value.toLocaleLowerCase().includes(query) || option.searchText?.toLocaleLowerCase().includes(query)
  }), [options, search])

  useEffect(() => {
    if (!open) return
    if (searchable) searchRef.current?.focus()
    else optionRefs.current[activeIndex]?.focus()
  }, [open, activeIndex, searchable])

  useEffect(() => {
    if (open && searchable) setActiveIndex(visible.find(({ option }) => !option.disabled)?.index ?? -1)
  }, [search])

  useEffect(() => {
    if (!open) return
    const closeOnOutsidePointer = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('pointerdown', closeOnOutsidePointer)
    return () => document.removeEventListener('pointerdown', closeOnOutsidePointer)
  }, [open])

  const openAt = (index: number) => {
    const firstEnabled = options.findIndex(option => !option.disabled)
    const startIndex = index >= 0 && !options[index]?.disabled ? index : Math.max(firstEnabled, 0)
    const bounds = triggerRef.current?.getBoundingClientRect()
    if (bounds) setPlacement(window.innerHeight - bounds.bottom < 280 && bounds.top > window.innerHeight - bounds.bottom ? 'top' : 'bottom')
    setSearch('')
    setActiveIndex(startIndex)
    setOpen(true)
  }

  const moveActive = (direction: -1 | 1) => {
    const enabled = visible.filter(({ option }) => !option.disabled).map(({ index }) => index)
    if (!enabled.length) return
    const current = enabled.indexOf(activeIndex)
    setActiveIndex(enabled[(current + direction + enabled.length) % enabled.length])
  }

  const selectOption = (option: DropdownOption) => {
    if (option.disabled) return
    onChange(option.value)
    setOpen(false)
    setSearch('')
    triggerRef.current?.focus()
  }

  const handleTriggerKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      if (open) moveActive(1)
      else openAt(selectedIndex)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      if (open) moveActive(-1)
      else openAt(selectedIndex >= 0 ? selectedIndex : options.length - 1)
    } else if (event.key === 'Escape' && open) {
      event.preventDefault()
      setOpen(false)
    }
  }

  const handleOptionKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      moveActive(event.key === 'ArrowDown' ? 1 : -1)
    } else if (event.key === 'Home' || event.key === 'End') {
      event.preventDefault()
      const enabled = visible.filter(({ option }) => !option.disabled).map(({ index }) => index)
      const index = event.key === 'Home' ? enabled[0] : enabled[enabled.length - 1]
      if (index !== undefined) setActiveIndex(index)
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      const option = options[activeIndex]
      if (option) selectOption(option)
    } else if (event.key === 'Escape') {
      event.preventDefault()
      setOpen(false)
      triggerRef.current?.focus()
    } else if (event.key === 'Tab') setOpen(false)
  }

  return <div className={`dropdown ${className}`.trim()} ref={rootRef} onBlur={event => {
    if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setOpen(false)
  }}>
    <button ref={triggerRef} type="button" className="dropdown-trigger" aria-label={ariaLabel} aria-labelledby={ariaLabelledBy} aria-haspopup="listbox" aria-expanded={open} aria-controls={`${id}-listbox`} disabled={disabled} onClick={() => open ? setOpen(false) : openAt(selectedIndex)} onKeyDown={handleTriggerKeyDown}>
      <span className={selectedOption ? '' : 'dropdown-placeholder'}>{selectedOption?.selectedLabel ?? selectedOption?.label ?? placeholder}</span><ChevronDown size={16} aria-hidden="true"/>
    </button>
    {open && <div className="dropdown-menu" data-placement={placement}>
      {searchable&&<div className="dropdown-search"><input ref={searchRef} type="search" role="combobox" aria-label={`Търсене в ${ariaLabel}`} aria-controls={`${id}-listbox`} aria-expanded="true" placeholder={searchPlaceholder} value={search} onChange={event=>setSearch(event.target.value)} onKeyDown={event=>{
        if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();moveActive(event.key==='ArrowDown'?1:-1)}
        else if(event.key==='Enter'){event.preventDefault();const option=options[activeIndex];if(option)selectOption(option)}
        else if(event.key==='Escape'){event.preventDefault();setOpen(false);triggerRef.current?.focus()}
        else if(event.key==='Tab')setOpen(false)
      }}/></div>}
      <div id={`${id}-listbox`} role="listbox" aria-label={ariaLabel}>
        {visible.map(({option,index}) => <button key={option.value} ref={node => { optionRefs.current[index] = node }} id={`${id}-option-${index}`} type="button" role="option" aria-selected={option.value === value} aria-disabled={option.disabled || undefined} tabIndex={index === activeIndex ? 0 : -1} className={`dropdown-option${index === activeIndex ? ' active' : ''}${option.value === value ? ' selected' : ''}`} disabled={option.disabled} onClick={() => selectOption(option)} onKeyDown={handleOptionKeyDown} onMouseEnter={() => setActiveIndex(index)}>
          <span>{option.label}</span>{option.value === value && <Check size={16} aria-hidden="true"/>}
        </button>)}
        {searchable&&visible.length===0&&<div className="dropdown-empty">Няма намерени резултати.</div>}
      </div>
    </div>}
  </div>
}
