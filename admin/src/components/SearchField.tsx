import { Search } from 'lucide-react'
import type { ChangeEventHandler } from 'react'

type SearchFieldProps = {
  value: string
  onChange: ChangeEventHandler<HTMLInputElement>
  placeholder: string
  ariaLabel: string
  className?: string
  disabled?: boolean
}

/** Shared search control used by catalog tables and other filterable lists. */
export function SearchField({ value, onChange, placeholder, ariaLabel, className = '', disabled = false }: SearchFieldProps) {
  return <label className={`search-wrap ${className}`.trim()}>
    <Search size={17} aria-hidden="true"/>
    <input type="search" value={value} onChange={onChange} placeholder={placeholder} aria-label={ariaLabel} disabled={disabled}/>
  </label>
}
