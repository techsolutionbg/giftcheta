import { useEffect, useRef } from 'react'
import type { ReactNode } from 'react'

type SelectionControlProps = {
  checked: boolean
  onChange: (checked: boolean) => void
  variant?: 'checkbox' | 'switch'
  label?: ReactNode
  description?: ReactNode
  ariaLabel?: string
  indeterminate?: boolean
  disabled?: boolean
  className?: string
}

/** Shared, theme-aware checkbox and switch used throughout the admin panel. */
export function SelectionControl({ checked, onChange, variant = 'switch', label, description, ariaLabel, indeterminate = false, disabled = false, className = '' }: SelectionControlProps) {
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (inputRef.current) inputRef.current.indeterminate = indeterminate
  }, [indeterminate])

  return <label className={`selection-control selection-control-${variant}${label ? ' has-label' : ' control-only'}${disabled ? ' is-disabled' : ''} ${className}`.trim()}>
    <input ref={inputRef} type="checkbox" checked={checked} disabled={disabled} aria-label={ariaLabel} aria-checked={indeterminate ? 'mixed' : checked} role={variant === 'switch' ? 'switch' : undefined} onChange={event => onChange(event.target.checked)}/>
    <span className="selection-control-visual" aria-hidden="true"/>
    {label && <span className="selection-control-copy"><span className="selection-control-label">{label}</span>{description && <span className="selection-control-description">{description}</span>}</span>}
  </label>
}
