import { useAdminSettings, formatMoneyInput, normalizeMoneyInput } from '../lib/adminSettings'

type MoneyInputProps = {
  value: string | number | null | undefined
  onChange: (value: string) => void
  ariaLabelledBy?: string
  placeholder?: string
  disabled?: boolean
}

/** Currency input that displays the configured decimal separator but keeps API values normalized. */
export function MoneyInput({ value, onChange, ariaLabelledBy, placeholder, disabled }: MoneyInputProps) {
  const { settings } = useAdminSettings()
  return <input
    type="text"
    inputMode="decimal"
    value={formatMoneyInput(value, settings.decimalSeparator)}
    onChange={event => onChange(normalizeMoneyInput(event.target.value, settings.decimalSeparator))}
    placeholder={placeholder || `0${settings.decimalSeparator}00`}
    aria-labelledby={ariaLabelledBy}
    disabled={disabled}
  />
}
