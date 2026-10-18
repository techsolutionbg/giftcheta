import type { ReactNode } from 'react'

type TablePanelProps = {
  children: ReactNode
  className?: string
}

/** Shared viewport-filling surface for catalog tables, filters and empty states. */
export function TablePanel({ children, className = '' }: TablePanelProps) {
  return <section className={`panel table-panel ${className}`.trim()}>{children}</section>
}
