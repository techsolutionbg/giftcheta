import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Save } from 'lucide-react'

type SaveButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode
  loading?: boolean
  loadingLabel?: string
}

export function SaveButton({ children, loading = false, loadingLabel = 'Запазване…', className = '', type = 'submit', ...props }: SaveButtonProps) {
  return <button {...props} type={type} className={`primary-button save-record-button ${className}`.trim()}>
    <Save size={16}/><span>{loading ? loadingLabel : children}</span>
  </button>
}
