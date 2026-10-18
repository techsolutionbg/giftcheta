import type { HTMLAttributes, ReactNode, Ref } from 'react'
import { flexRender } from '@tanstack/react-table'
import type { Row, Table } from '@tanstack/react-table'
import { ArrowDown, ArrowUp } from 'lucide-react'

type DataTableProps<TData> = {
  table: Table<TData>
  leadingHeader?: ReactNode
  renderLeadingCell?: (row: Row<TData>) => ReactNode
  getRowLabel?: (row: Row<TData>) => string
  getRowProps?: (row: Row<TData>) => HTMLAttributes<HTMLTableRowElement> & { ref?: Ref<HTMLTableRowElement> }
}

/** Shared renderer for admin data tables, including optional selection columns. */
export function DataTable<TData>({ table, leadingHeader, renderLeadingCell, getRowLabel, getRowProps }: DataTableProps<TData>) {
  return <div className="table-scroll"><table className="data-table">
    <thead>{table.getHeaderGroups().map(group => <tr key={group.id}>
      {renderLeadingCell && <th className="check-col">{leadingHeader}</th>}
      {group.headers.map(header => <th key={header.id} onClick={header.column.getCanSort() ? header.column.getToggleSortingHandler() : undefined} className={header.column.getCanSort() ? 'sortable' : ''}>
        {flexRender(header.column.columnDef.header, header.getContext())}
        {header.column.getIsSorted() === 'asc' ? <ArrowUp size={12}/> : header.column.getIsSorted() === 'desc' ? <ArrowDown size={12}/> : null}
      </th>)}
    </tr>)}</thead>
    <tbody>{table.getRowModel().rows.map(row => {
      const { ref, ...rowProps } = getRowProps?.(row) ?? {}
      return <tr key={row.id} {...rowProps} ref={ref} aria-label={getRowLabel?.(row)}>
      {renderLeadingCell && <td className="check-col">{renderLeadingCell(row)}</td>}
      {row.getVisibleCells().map(cell => <td key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</td>)}
    </tr>
    })}</tbody>
  </table></div>
}
