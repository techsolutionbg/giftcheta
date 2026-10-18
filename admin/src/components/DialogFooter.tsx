import type { HTMLAttributes, ReactNode } from 'react'

type DialogFooterProps = HTMLAttributes<HTMLElement> & {
  children: ReactNode
}

/** Shared action footer for dialogs. Buttons keep their natural, label-based widths. */
export function DialogFooter({ children, className = '', ...props }: DialogFooterProps) {
  return <footer {...props} className={`editor-footer dialog-footer ${className}`.trim()}>{children}</footer>
}
