import { useState, type ReactNode } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import { SidePanel } from './SidePanel'

type TablePanelProps = {
  children: ReactNode
  className?: string
  filters?: ReactNode
  filtersTitle?: string
}

/** Shared viewport-filling surface for catalog tables, filters and empty states. */
export function TablePanel({ children, className = '', filters, filtersTitle = 'Филтри' }: TablePanelProps) {
  const [filtersOpen, setFiltersOpen] = useState(false)

  return <section className={`panel table-panel ${className}`.trim()}>
    {filters && <>
      <div className="table-mobile-filter-bar">
        <button type="button" className="outline-button table-mobile-filter-trigger" onClick={() => setFiltersOpen(true)}>
          <SlidersHorizontal size={16}/>
          Филтри
        </button>
      </div>
      <SidePanel open={filtersOpen} onOpenChange={setFiltersOpen} side="right" title={filtersTitle} panelClassName="table-filters-panel">
        <div className="table-mobile-filters">{filters}</div>
      </SidePanel>
    </>}
    {children}
  </section>
}
