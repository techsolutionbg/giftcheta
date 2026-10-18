import { useEffect, useId, useRef, useState } from 'react'
import type * as React from 'react'
import { Check, ChevronDown } from 'lucide-react'

export type DropdownOption = {
  value: string
  label: string
  disabled?: boolean
}

type DropdownProps = {
  value: string
  options: DropdownOption[]
  onChange: (value: string) => void
  ariaLabel: string
  placeholder?: string
  className?: string
  disabled?: boolean
  ariaLabelledBy?: string
}

export function Dropdown({ value, options, onChange, ariaLabel, placeholder = 'Изберете…', className = '', disabled = false, ariaLabelledBy }: DropdownProps) {
  const id = useId()
  const rootRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const optionRefs = useRef<Array<HTMLButtonElement | null>>([])
  const selectedIndex = options.findIndex(option => option.value === value)
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(Math.max(selectedIndex, 0))
  const [placement, setPlacement] = useState<'bottom' | 'top'>('bottom')
  const selectedOption = selectedIndex >= 0 ? options[selectedIndex] : undefined

  useEffect(() => {
    if (open) optionRefs.current[activeIndex]?.focus()
  }, [open, activeIndex])

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
    setActiveIndex(startIndex)
    setOpen(true)
  }

  const moveActive = (direction: -1 | 1) => {
    if (!options.length) return
    let index = activeIndex
    for (let attempt = 0; attempt < options.length; attempt += 1) {
      index = (index + direction + options.length) % options.length
      if (!options[index].disabled) {
        setActiveIndex(index)
        return
      }
    }
  }

  const selectOption = (option: DropdownOption) => {
    if (option.disabled) return
    onChange(option.value)
    setOpen(false)
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
      const index = event.key === 'Home'
        ? options.findIndex(option => !option.disabled)
        : options.reduce((last, option, current) => option.disabled ? last : current, -1)
      if (index >= 0) setActiveIndex(index)
    } else if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      const option = options[activeIndex]
      if (option) selectOption(option)
    } else if (event.key === 'Escape') {
      event.preventDefault()
      setOpen(false)
      triggerRef.current?.focus()
    } else if (event.key === 'Tab') {
      setOpen(false)
    }
  }

  return <div className={`dropdown ${className}`.trim()} ref={rootRef} onBlur={event => {
    if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setOpen(false)
  }}>
    <button
      ref={triggerRef}
      type="button"
      className="dropdown-trigger"
      aria-label={ariaLabel}
      aria-labelledby={ariaLabelledBy}
      aria-haspopup="listbox"
      aria-expanded={open}
      aria-controls={`${id}-listbox`}
      disabled={disabled}
      onClick={() => open ? setOpen(false) : openAt(selectedIndex)}
      onKeyDown={handleTriggerKeyDown}
    >
      <span className={selectedOption ? '' : 'dropdown-placeholder'}>{selectedOption?.label ?? placeholder}</span>
      <ChevronDown size={16} aria-hidden="true"/>
    </button>
    {open && <div className="dropdown-menu" data-placement={placement} id={`${id}-listbox`} role="listbox" aria-label={ariaLabel}>
      {options.map((option, index) => <button
        key={option.value}
        ref={node => { optionRefs.current[index] = node }}
        id={`${id}-option-${index}`}
        type="button"
        role="option"
        aria-selected={option.value === value}
        aria-disabled={option.disabled || undefined}
        tabIndex={index === activeIndex ? 0 : -1}
        className={`dropdown-option${index === activeIndex ? ' active' : ''}${option.value === value ? ' selected' : ''}`}
        disabled={option.disabled}
        onClick={() => selectOption(option)}
        onKeyDown={handleOptionKeyDown}
        onMouseEnter={() => setActiveIndex(index)}
      >
        <span>{option.label}</span>
        {option.value === value && <Check size={16} aria-hidden="true"/>}
      </button>)}
    </div>}
  </div>
}
