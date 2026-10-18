import { api, json } from '../lib/api'
import type { Customer, PageMeta, ToastState } from '../types/admin'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import type { SortingState } from '@tanstack/react-table'
import { Ban, ChevronLeft, ChevronRight, Pencil, Plus, RotateCcw, ShieldCheck, Trash2, UserRound } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { Breadcrumbs } from '../components/Navigation'
import { SearchField } from '../components/SearchField'
import { Dropdown } from '../components/Dropdown'
import { TablePanel } from '../components/TablePanel'
import { DataTable } from '../components/DataTable'
import { SelectionControl } from '../components/SelectionControl'
import { EmptyState, RecordName, StatusBadge } from '../components/CatalogComponents'
import { ActionMenu } from '../components/ActionMenu'
import { useConfirmation } from '../components/UnsavedChanges'
import { CustomerEditor } from './CustomerEditor'

type CustomerAction = 'activate' | 'deactivate' | 'block' | 'trash' | 'restore' | 'permanent'
const statusLabels: Record<string, string> = { active: 'Активен', inactive: 'Неактивен', blocked: 'Блокиран' }

export function CustomersPage({ notify }: { notify: (message: string, kind?: ToastState['kind']) => void }) {
  const location = useLocation()
  const navigate = useNavigate()
  const confirm = useConfirmation()
  const [rows, setRows] = useState<Customer[]>([])
  const [meta, setMeta] = useState<PageMeta>({ page: 1, per_page: 20, total: 0, pages: 0 })
  const [search, setSearch] = useState(() => new URLSearchParams(location.search).get('search') || '')
  const [statusFilter, setStatusFilter] = useState('')
  const [page, setPage] = useState(1)
  const [sorting, setSorting] = useState<SortingState>([{ id: 'created_at', desc: true }])
  const [selected, setSelected] = useState<number[]>([])
  const [busy, setBusy] = useState(true)
  const [error, setError] = useState('')
  const routeMatch = location.pathname.match(/^\/admin\/customers\/(\d+)\/edit$/)
  const routeCreate = location.pathname === '/admin/customers/new'
  const params = new URLSearchParams(location.search)
  const dialog = routeCreate ? 'create' : routeMatch ? 'edit' : params.get('dialog')
  const routeCustomerId = routeMatch ? Number(routeMatch[1]) : null
  const queryCustomerId = dialog === 'edit' ? Number(params.get('id')) : null
  const editingCustomerId = routeCustomerId ?? (Number.isInteger(queryCustomerId) && queryCustomerId! > 0 ? queryCustomerId : null)

  useEffect(() => {
    if (!routeCreate && !routeMatch) return
    const next = new URLSearchParams(location.search)
    next.set('dialog', routeCreate ? 'create' : 'edit')
    if (routeMatch) next.set('id', routeMatch[1])
    else next.delete('id')
    navigate(`/admin/customers?${next.toString()}`, { replace: true })
  }, [location.pathname, location.search, navigate, routeCreate, routeMatch?.[1]])

  const updateDialogUrl = (mode: 'create' | 'edit' | null, id?: number, replace = false) => {
    const next = new URLSearchParams(location.search)
    if (mode) {
      next.set('dialog', mode)
      if (id) next.set('id', String(id))
      else next.delete('id')
    } else {
      next.delete('dialog')
      next.delete('id')
    }
    navigate(`${location.pathname}${next.size ? `?${next.toString()}` : ''}`, { replace })
  }
  const openCreate = () => updateDialogUrl('create')
  const openEdit = (customer: Customer) => updateDialogUrl('edit', customer.id)
  const closeEditor = () => updateDialogUrl(null)
  const isTrash = statusFilter === 'trash'
  const sort = sorting[0]

  const load = useCallback(async () => {
    setBusy(true)
    try {
      const params = new URLSearchParams({ page: String(page), per_page: '20', sort: sort?.id || 'created_at', direction: sort?.desc ? 'desc' : 'asc' })
      if (search.trim()) params.set('search', search.trim())
      if (isTrash) params.set('trash', 'only')
      else if (statusFilter) params.set('status', statusFilter)
      const result = await api<{ data: Customer[]; meta: PageMeta }>(`/customers?${params}`)
      if (page > Math.max(1, result.meta.pages)) { setPage(Math.max(1, result.meta.pages)); return }
      setRows(result.data)
      setMeta(result.meta)
      setSelected([])
      setError('')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Списъкът с клиенти не можа да се зареди.')
    } finally {
      setBusy(false)
    }
  }, [page, search, statusFilter, isTrash, sort?.id, sort?.desc])

  useEffect(() => {
    const timeout = window.setTimeout(() => void load(), 180)
    return () => window.clearTimeout(timeout)
  }, [load])
  useEffect(() => {
    const nextSearch = new URLSearchParams(location.search).get('search') || ''
    setSearch(current => current === nextSearch ? current : nextSearch)
  }, [location.search])

  const perform = useCallback(async (action: CustomerAction, ids: number[]) => {
    if (!ids.length) return
    const messages: Record<CustomerAction, { title: string; message: string; confirmLabel: string; danger?: boolean; success: string }> = {
      activate: { title: 'Активиране на клиенти?', message: `Ще бъдат активирани ${ids.length} избрани клиента.`, confirmLabel: 'Активирай', success: 'Клиентите са активирани.' },
      deactivate: { title: 'Деактивиране на клиенти?', message: `Ще бъдат деактивирани ${ids.length} избрани клиента.`, confirmLabel: 'Деактивирай', success: 'Клиентите са деактивирани.' },
      block: { title: 'Блокиране на клиенти?', message: `Ще бъде забранен бъдещият вход за ${ids.length} избрани клиента.`, confirmLabel: 'Блокирай', danger: true, success: 'Клиентите са блокирани.' },
      trash: { title: 'Преместване в кошчето?', message: `${ids.length} избрани клиента ще бъдат преместени в кошчето и могат да бъдат възстановени.`, confirmLabel: 'Премести в кошчето', danger: true, success: 'Клиентите са преместени в кошчето.' },
      restore: { title: 'Възстановяване на клиенти?', message: `Ще бъдат възстановени ${ids.length} избрани клиента.`, confirmLabel: 'Възстанови', success: 'Клиентите са възстановени.' },
      permanent: { title: 'Изтриване завинаги?', message: `${ids.length} избрани клиента ще бъдат изтрити окончателно, ако нямат свързани записи.`, confirmLabel: 'Изтрий завинаги', danger: true, success: 'Клиентите са изтрити завинаги.' },
    }
    const copy = messages[action]
    if (!(await confirm({ title: copy.title, message: copy.message, confirmLabel: copy.confirmLabel, danger: copy.danger }))) return
    try {
      const result = await api<{ data: { affected: number; failed_ids: number[] } }>('/customers/bulk', { method: 'POST', body: json({ action, ids }) })
      const failed = result.data.failed_ids?.length || 0
      notify(failed ? `${copy.success} Неуспешни записи: ${failed}.` : copy.success, failed ? 'error' : 'success')
      void load()
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : 'Действието не беше изпълнено.', 'error')
    }
  }, [confirm, load, notify])

  const columns = useMemo(() => {
    const helper = createColumnHelper<Customer>()
    return [
      helper.accessor(row => `${row.first_name} ${row.last_name}`.trim(), { id: 'name', header: 'Име', cell: info => <RecordName name={info.getValue()} onClick={() => openEdit(info.row.original)}/> }),
      helper.accessor('email', { header: 'Имейл', enableSorting: false, cell: info => <span>{info.getValue() || '—'}</span> }),
      helper.accessor('phone', { header: 'Телефон', enableSorting: false, cell: info => <span>{info.getValue() || '—'}</span> }),
      helper.accessor('status', { header: 'Статус', cell: info => <StatusBadge status={info.getValue()} label={statusLabels[info.getValue()] || info.getValue()}/> }),
      helper.accessor('created_at', { header: 'Създаден', cell: info => <span>{new Intl.DateTimeFormat('bg-BG', { dateStyle: 'medium' }).format(new Date(info.getValue().replace(' ', 'T')))}</span> }),
      helper.display({ id: 'actions', header: 'Действия', enableSorting: false, cell: info => {
        const customer = info.row.original
        const actions = isTrash
          ? [{ label: 'Възстанови', icon: RotateCcw, onSelect: () => void perform('restore', [customer.id]) }, { label: 'Изтрий завинаги', icon: Trash2, danger: true, onSelect: () => void perform('permanent', [customer.id]) }]
          : [
              { label: 'Редактирай', icon: Pencil, onSelect: () => openEdit(customer) },
              ...(customer.status === 'blocked' ? [{ label: 'Разблокирай', icon: ShieldCheck, onSelect: () => void perform('activate', [customer.id]) }] : [{ label: customer.status === 'active' ? 'Деактивирай' : 'Активирай', icon: customer.status === 'active' ? Ban : ShieldCheck, onSelect: () => void perform(customer.status === 'active' ? 'deactivate' : 'activate', [customer.id]) }, { label: 'Блокирай достъпа', icon: Ban, onSelect: () => void perform('block', [customer.id]) }]),
              { label: 'Премести в кошчето', icon: Trash2, danger: true, onSelect: () => void perform('trash', [customer.id]) },
            ]
        return <ActionMenu label={`Действия за ${customer.first_name} ${customer.last_name}`} items={actions}/> 
      } }),
    ]
  }, [isTrash, navigate, openEdit, perform])

  const table = useReactTable({ data: rows, columns, getCoreRowModel: getCoreRowModel(), manualSorting: true, state: { sorting }, onSortingChange: setSorting })
  const filteredOptions = [
    { value: '', label: 'Всички статуси' },
    { value: 'active', label: 'Активни' },
    { value: 'inactive', label: 'Неактивни' },
    { value: 'blocked', label: 'Блокирани' },
    { value: 'trash', label: 'Кошче' },
  ]

  return <div className="page-content catalog-page customer-page">
    <div className="page-heading"><div><div className="eyebrow">АДМИНИСТРАЦИЯ / КЛИЕНТИ</div><h1>{isTrash ? 'Кошче с клиенти' : 'Клиенти'}</h1><Breadcrumbs/><p>{isTrash ? 'Възстановете изтрит клиент или го премахнете окончателно.' : 'Управлявайте клиентските профили, контактите и адресите.'}</p></div>{!isTrash&&<button className="primary-button" onClick={openCreate}><Plus size={17}/> Добави клиент</button>}</div>
    <TablePanel className="customer-table-panel" filters={<><SearchField value={search} onChange={event=>{setSearch(event.target.value);setPage(1)}} placeholder="Търси име, имейл или телефон…" ariaLabel="Търсене на клиенти"/><Dropdown className="filter-dropdown" value={statusFilter} ariaLabel="Филтър по статус" onChange={value=>{setStatusFilter(value);setPage(1)}} options={filteredOptions}/></>} filtersTitle="Филтри на клиентите">
      <div className="table-toolbar"><SearchField value={search} onChange={event=>{setSearch(event.target.value);setPage(1)}} placeholder="Търси име, имейл или телефон…" ariaLabel="Търсене на клиенти"/><Dropdown className="filter-dropdown" value={statusFilter} ariaLabel="Филтър по статус" onChange={value=>{setStatusFilter(value);setPage(1)}} options={filteredOptions}/><span className="toolbar-spacer"/>
        {selected.length>0&&(isTrash?<><button className="outline-button bulk-action-button" onClick={()=>void perform('restore',selected)}>Възстанови ({selected.length})</button><button className="outline-button bulk-action-button danger" onClick={()=>void perform('permanent',selected)}>Изтрий завинаги ({selected.length})</button></>:<><button className="outline-button bulk-action-button" onClick={()=>void perform('activate',selected)}>Активирай ({selected.length})</button><button className="outline-button bulk-action-button" onClick={()=>void perform('deactivate',selected)}>Деактивирай ({selected.length})</button><button className="outline-button bulk-action-button" onClick={()=>void perform('block',selected)}>Блокирай ({selected.length})</button><button className="outline-button bulk-action-button danger" onClick={()=>void perform('trash',selected)}>В кошчето ({selected.length})</button></>)}<span className="customer-count">{meta.total} клиента</span>
      </div>
      {error?<div className="table-error">{error}<button onClick={()=>void load()}>Опитай отново</button></div>:busy?<div className="loading-state"><span className="spinner"/> Зареждаме клиентите…</div>:rows.length===0?<EmptyState icon={<UserRound/>} title={isTrash?'Кошчето е празно':search?'Няма намерени клиенти':'Все още няма клиенти'} description={isTrash?'Преместените тук клиенти могат да бъдат възстановени или изтрити завинаги.':search?'Променете търсенето или филтрите.':'Добавете клиентски профил, за да управлявате контактната информация и адресите му.'} action={!isTrash&&!search&&<button className="primary-button" onClick={openCreate}><Plus size={16}/> Добави клиент</button>}/>:<>
        <DataTable table={table} leadingHeader={<SelectionControl className="check-col" variant="switch" checked={selected.length===rows.length&&rows.length>0} indeterminate={selected.length>0&&selected.length<rows.length} ariaLabel="Избери всички клиенти" onChange={checked=>setSelected(checked?rows.map(customer=>customer.id):[])}/>} renderLeadingCell={row=><SelectionControl className="check-col" variant="switch" checked={selected.includes(row.original.id)} ariaLabel={`Избери клиент ${row.original.first_name} ${row.original.last_name}`} onChange={checked=>setSelected(checked?[...selected,row.original.id]:selected.filter(id=>id!==row.original.id))}/>} getRowLabel={row=>`${row.original.first_name} ${row.original.last_name}`}/>
        <div className="table-pagination"><span>Показани <strong>{(page-1)*meta.per_page+1}–{Math.min(page*meta.per_page,meta.total)}</strong> от <strong>{meta.total}</strong> клиента</span><div><button disabled={page<=1} onClick={()=>setPage(current=>Math.max(1,current-1))} title="Предишна страница" aria-label="Предишна страница"><ChevronLeft size={17}/></button><span>Страница {page} от {Math.max(1,meta.pages)}</span><button disabled={page>=meta.pages} onClick={()=>setPage(current=>Math.min(meta.pages,current+1))} title="Следваща страница" aria-label="Следваща страница"><ChevronRight size={17}/></button></div></div>
      </>}
    </TablePanel>
    {dialog==='create'&&<CustomerEditor key="new-customer" customerId={null} onClose={closeEditor} onSaved={id=>{updateDialogUrl('edit',id,true);void load()}} notify={notify}/>}
    {dialog==='edit'&&editingCustomerId!==null&&<CustomerEditor key={`customer-${editingCustomerId}`} customerId={editingCustomerId} onClose={closeEditor} onSaved={()=>void load()} notify={notify}/>}
  </div>
}
