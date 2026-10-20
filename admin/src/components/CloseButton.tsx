import type { ButtonHTMLAttributes } from 'react'
import { X } from 'lucide-react'

type CloseButtonProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'aria-label' | 'children'> & {
  'aria-label': string
}

export function CloseButton({ className = '', type = 'button', ...props }: CloseButtonProps) {
  return <button {...props} type={type} className={`site-close-button ${className}`.trim()}>
    <X aria-hidden="true" size={30} strokeWidth={2.2}/>
  </button>
}
