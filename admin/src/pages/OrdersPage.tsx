import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { ArrowLeft, Ban, Check, ChevronLeft, ChevronRight, Clock3, CreditCard, Eye, Mail, PackageCheck, Plus, RefreshCw, ShoppingBag } from 'lucide-react'
import { api, json } from '../lib/api'
import { formatMoney, useAdminSettings } from '../lib/adminSettings'
import type { Order, OrderPaymentStatus, OrderRow, OrderStatus, PageMeta, ToastState } from '../types/admin'
import { Breadcrumbs } from '../components/Navigation'
import { SearchField } from '../components/SearchField'
import { Dropdown } from '../components/Dropdown'
import { TablePanel } from '../components/TablePanel'
import { DataTable } from '../components/DataTable'
import { SelectionControl } from '../components/SelectionControl'
import { ActionMenu } from '../components/ActionMenu'
import { EmptyState, RecordName } from '../components/CatalogComponents'
import { useConfirmation } from '../components/UnsavedChanges'
import { OrderCreateDialog } from './OrderCreateDialog'

const statusLabels:Record<OrderStatus,string>={new:'Нова',confirmed:'Потвърдена',processing:'Обработва се',shipped:'Изпратена',delivered:'Доставена',cancelled:'Анулирана'}
const paymentLabels:Record<OrderPaymentStatus,string>={unpaid:'Неплатена',paid:'Платена',refunded:'Възстановена'}
const fulfillmentLabels:Record<string,string>={unfulfilled:'Неизпълнена',in_progress:'В процес',shipped:'Изпратена',fulfilled:'Изпълнена'}
const nextStatus:Partial<Record<OrderStatus,OrderStatus>>={new:'confirmed',confirmed:'processing',processing:'shipped',shipped:'delivered'}

function dateLabel(value:string){return new Intl.DateTimeFormat('bg-BG',{dateStyle:'medium',timeStyle:'short'}).format(new Date(value.replace(' ','T')))}
function OrderBadge({status,label}:{status:string;label:string}){return <span className={`status-badge order-state-${status}`} data-status={status}>{label}</span>}

export function OrdersPage({notify}:{notify:(message:string,kind?:ToastState['kind'])=>void}){
  const {id}=useParams();return id?<OrderDetailPage orderId={id} notify={notify}/>:<OrdersListPage notify={notify}/>
}

function OrdersListPage({notify}:{notify:(message:string,kind?:ToastState['kind'])=>void}){
  const navigate=useNavigate();const confirm=useConfirmation();const {settings}=useAdminSettings()
  const [rows,setRows]=useState<OrderRow[]>([]);const [meta,setMeta]=useState<PageMeta>({page:1,per_page:20,total:0,pages:0});const [search,setSearch]=useState('');const [status,setStatus]=useState('');const [payment,setPayment]=useState('');const [dateFrom,setDateFrom]=useState('');const [dateTo,setDateTo]=useState('');const [page,setPage]=useState(1);const [sorting,setSorting]=useState([{id:'created_at',desc:true}]);const [selected,setSelected]=useState<number[]>([]);const [busy,setBusy]=useState(true);const [error,setError]=useState('');const [createOpen,setCreateOpen]=useState(false)
  const sort=sorting[0]
  const load=useCallback(async()=>{
    setBusy(true)
    try{const params=new URLSearchParams({page:String(page),per_page:'20',sort:sort?.id||'created_at',direction:sort?.desc?'desc':'asc'});if(search.trim())params.set('search',search.trim());if(status)params.set('status',status);if(payment)params.set('payment_status',payment);if(dateFrom)params.set('date_from',dateFrom);if(dateTo)params.set('date_to',dateTo);const result=await api<{data:OrderRow[];meta:PageMeta}>(`/orders?${params}`);if(page>Math.max(1,result.meta.pages)){setPage(Math.max(1,result.meta.pages));return}setRows(result.data);setMeta(result.meta);setSelected([]);setError('')}
    catch(reason){setError(reason instanceof Error?reason.message:'Поръчките не можаха да се заредят.')}
    finally{setBusy(false)}
  },[page,search,status,payment,dateFrom,dateTo,sort?.id,sort?.desc])
  useEffect(()=>{const timeout=window.setTimeout(()=>void load(),120);return()=>window.clearTimeout(timeout)},[load])

  const bulkConfirm=async()=>{
    const ids=selected.filter(id=>rows.some(row=>row.id===id&&row.status==='new'));if(!ids.length){notify('Изберете нови поръчки за потвърждение.','error');return}
    if(!(await confirm({title:'Потвърждаване на поръчки?',message:`Ще бъдат потвърдени ${ids.length} избрани нови поръчки.`,confirmLabel:'Потвърди'})))return
    try{await api('/orders/bulk',{method:'POST',body:json({action:'confirm',ids})});notify('Избраните поръчки са потвърдени.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Поръчките не бяха потвърдени.','error')}
  }
  const cancelOrder=async(order:OrderRow)=>{
    if(!(await confirm({title:'Анулиране на поръчката?',message:`Поръчка ${order.order_number} ще бъде анулирана, а проследяваните наличности ще бъдат възстановени.`,confirmLabel:'Анулирай',danger:true})))return
    try{await api(`/orders/${order.id}/cancel`,{method:'POST',body:'{}'});notify(`Поръчка ${order.order_number} е анулирана.`);void load()}catch(reason){notify(reason instanceof Error?reason.message:'Поръчката не беше анулирана.','error')}
  }
  const columns=useMemo(()=>{
    const helper=createColumnHelper<OrderRow>()
    return [
      helper.accessor('order_number',{header:'Номер',cell:info=><RecordName name={info.getValue()} onClick={()=>navigate(`/admin/orders/${info.row.original.id}`)}/>}),
      helper.accessor(row=>`${row.customer_first_name} ${row.customer_last_name}`.trim(),{id:'customer',header:'Клиент',cell:info=><div className="order-customer-cell"><span>{info.getValue()}</span><small>{info.row.original.customer_email}</small></div>}),
      helper.accessor('created_at',{header:'Дата',cell:info=><span>{dateLabel(info.getValue())}</span>}),
      helper.accessor('item_count',{header:'Артикули',cell:info=><span>{info.getValue()}</span>}),
      helper.accessor('grand_total',{header:'Крайна сума',cell:info=><strong>{formatMoney(info.getValue(),settings.decimalSeparator)}</strong>}),
      helper.accessor('status',{header:'Статус',cell:info=><OrderBadge status={info.getValue()} label={statusLabels[info.getValue()]}/>}),
      helper.accessor('payment_status',{header:'Плащане',cell:info=><OrderBadge status={info.getValue()} label={paymentLabels[info.getValue()]}/>}),
      helper.accessor('fulfillment_status',{header:'Доставка',enableSorting:false,cell:info=><span className="order-fulfillment-label">{fulfillmentLabels[info.getValue()]||info.getValue()}</span>}),
      helper.display({id:'actions',header:'Действия',enableSorting:false,cell:info=>{const order=info.row.original;return <ActionMenu label={`Действия за ${order.order_number}`} items={[
        {label:'Преглед',icon:Eye,onSelect:()=>navigate(`/admin/orders/${order.id}`)},
        ...(order.status==='new'?[{label:'Потвърди',icon:Check,onSelect:async()=>{try{await api(`/orders/${order.id}/status`,{method:'POST',body:json({status:'confirmed'})});notify('Поръчката е потвърдена.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Статусът не се промени.','error')}}}]:[]),
        ...(['new','confirmed','processing'].includes(order.status)&&order.payment_status!=='paid'?[{label:'Анулирай',icon:Ban,danger:true,onSelect:()=>void cancelOrder(order)}]:[]),
      ]}/>}}),
    ]
  },[navigate,settings.decimalSeparator,notify,load])
  const table=useReactTable({data:rows,columns,getCoreRowModel:getCoreRowModel(),manualSorting:true,state:{sorting},onSortingChange:setSorting})
  const filterOptions=[{value:'',label:'Всички статуси'},...Object.entries(statusLabels).map(([value,label])=>({value,label}))]
  const paymentOptions=[{value:'',label:'Всички плащания'},...Object.entries(paymentLabels).map(([value,label])=>({value,label}))]
  const filters=<><SearchField value={search} onChange={event=>{setSearch(event.target.value);setPage(1)}} placeholder="Търси номер, имейл, име или телефон…" ariaLabel="Търсене на поръчки"/><Dropdown className="filter-dropdown" value={status} ariaLabel="Филтър по статус" onChange={value=>{setStatus(value);setPage(1)}} options={filterOptions}/><Dropdown className="filter-dropdown" value={payment} ariaLabel="Филтър по плащане" onChange={value=>{setPayment(value);setPage(1)}} options={paymentOptions}/><div className="order-date-filter"><label>От<input type="date" value={dateFrom} onChange={event=>{setDateFrom(event.target.value);setPage(1)}}/></label><label>До<input type="date" value={dateTo} onChange={event=>{setDateTo(event.target.value);setPage(1)}}/></label></div></>
  return <div className="page-content catalog-page orders-page">
    <div className="page-heading"><div><div className="eyebrow">АДМИНИСТРАЦИЯ / ПОРЪЧКИ</div><h1>Поръчки</h1><Breadcrumbs/><p>Преглеждайте поръчки, управлявайте статуса и проследявайте плащанията.</p></div><button className="primary-button" onClick={()=>setCreateOpen(true)}><Plus size={17}/> Нова поръчка</button></div>
    <TablePanel className="orders-table-panel" filters={filters} filtersTitle="Филтри на поръчките">
      <div className="table-toolbar"><SearchField value={search} onChange={event=>{setSearch(event.target.value);setPage(1)}} placeholder="Търси номер, имейл, име или телефон…" ariaLabel="Търсене на поръчки"/><Dropdown className="filter-dropdown" value={status} ariaLabel="Филтър по статус" onChange={value=>{setStatus(value);setPage(1)}} options={filterOptions}/><Dropdown className="filter-dropdown" value={payment} ariaLabel="Филтър по плащане" onChange={value=>{setPayment(value);setPage(1)}} options={paymentOptions}/><div className="order-toolbar-dates"><input aria-label="Начална дата" type="date" value={dateFrom} onChange={event=>{setDateFrom(event.target.value);setPage(1)}}/><input aria-label="Крайна дата" type="date" value={dateTo} onChange={event=>{setDateTo(event.target.value);setPage(1)}}/></div><span className="toolbar-spacer"/>{selected.length>0&&<button className="outline-button bulk-action-button" onClick={()=>void bulkConfirm()}>Потвърди избрани ({selected.length})</button>}<span className="customer-count">{meta.total} поръчки</span></div>
      {error?<div className="table-error">{error}<button onClick={()=>void load()}>Опитай отново</button></div>:busy?<div className="loading-state"><span className="spinner"/> Зареждаме поръчките…</div>:rows.length===0?<EmptyState icon={<ShoppingBag/>} title={search||status||payment?'Няма намерени поръчки':'Все още няма поръчки'} description={search||status||payment?'Променете търсенето или филтрите.':'Създайте първата поръчка от администрацията.'} action={!search&&!status&&!payment&&<button className="primary-button" onClick={()=>setCreateOpen(true)}><Plus size={16}/> Нова поръчка</button>}/>:<>
        <DataTable table={table} leadingHeader={<SelectionControl className="check-col" checked={selected.length===rows.length&&rows.length>0} indeterminate={selected.length>0&&selected.length<rows.length} ariaLabel="Избери всички поръчки" onChange={checked=>setSelected(checked?rows.filter(row=>row.status==='new').map(row=>row.id):[])}/>} renderLeadingCell={row=><SelectionControl className="check-col" checked={row.original.status==='new'&&selected.includes(row.original.id)} disabled={row.original.status!=='new'} ariaLabel={`Избери поръчка ${row.original.order_number}`} onChange={checked=>setSelected(current=>checked?[...current,row.original.id]:current.filter(id=>id!==row.original.id))}/>} getRowLabel={row=>row.original.order_number}/>
        <div className="table-pagination"><span>Показани <strong>{(page-1)*meta.per_page+1}–{Math.min(page*meta.per_page,meta.total)}</strong> от <strong>{meta.total}</strong> поръчки</span><div><button disabled={page<=1} onClick={()=>setPage(current=>Math.max(1,current-1))} title="Предишна страница" aria-label="Предишна страница"><ChevronLeft size={17}/></button><span>Страница {page} от {Math.max(1,meta.pages)}</span><button disabled={page>=meta.pages} onClick={()=>setPage(current=>Math.min(meta.pages,current+1))} title="Следваща страница" aria-label="Следваща страница"><ChevronRight size={17}/></button></div></div>
      </>}
    </TablePanel>
    {createOpen&&<OrderCreateDialog onClose={()=>setCreateOpen(false)} notify={notify}/>}
  </div>
}

function OrderDetailPage({orderId,notify}:{orderId:string;notify:(message:string,kind?:ToastState['kind'])=>void}){
  const navigate=useNavigate();const confirm=useConfirmation();const {settings}=useAdminSettings();const [order,setOrder]=useState<Order|null>(null);const [busy,setBusy]=useState(true);const [error,setError]=useState('');const [note,setNote]=useState('');const [savingNote,setSavingNote]=useState(false)
  const load=useCallback(async()=>{setBusy(true);try{const result=await api<{data:Order}>(`/orders/${orderId}`);setOrder(result.data);setNote(result.data.admin_note||'');setError('')}catch(reason){setError(reason instanceof Error?reason.message:'Поръчката не можа да се зареди.')}finally{setBusy(false)}},[orderId])
  useEffect(()=>{void load()},[load])
  const setStatus=async(status:OrderStatus)=>{try{await api(`/orders/${orderId}/status`,{method:'POST',body:json({status})});notify(`Статусът е променен на „${statusLabels[status]}“.`);void load()}catch(reason){notify(reason instanceof Error?reason.message:'Статусът не беше променен.','error')}}
  const cancel=async()=>{if(!order)return;if(!(await confirm({title:'Анулиране на поръчката?',message:`Поръчка ${order.order_number} ще бъде анулирана, а проследяваните наличности ще бъдат възстановени.`,confirmLabel:'Анулирай',danger:true})))return;try{await api(`/orders/${order.id}/cancel`,{method:'POST',body:'{}'});notify('Поръчката е анулирана.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Поръчката не беше анулирана.','error')}}
  const saveNote=async()=>{if(!order)return;setSavingNote(true);try{await api(`/orders/${order.id}`,{method:'PATCH',body:json({admin_note:note})});notify('Вътрешната бележка е запазена.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Бележката не беше запазена.','error')}finally{setSavingNote(false)}}
  const updatePayment=async()=>{if(!order)return;const next=order.payment_status==='unpaid'?'paid':'refunded';const question=next==='paid'?'Потвърдете, че наложеният платеж е получен.':'Потвърдете, че сумата е възстановена.';if(!(await confirm({title:next==='paid'?'Отбелязване на плащане?':'Отбелязване на възстановяване?',message:question,confirmLabel:next==='paid'?'Маркирай платена':'Маркирай възстановена'})))return;try{await api(`/orders/${order.id}/payment-status`,{method:'POST',body:json({payment_status:next})});notify(next==='paid'?'Плащането е отбелязано като получено.':'Плащането е отбелязано като възстановено.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Плащането не беше актуализирано.','error')}}
  const retryEmails=async()=>{if(!order)return;try{await api(`/orders/${order.id}/emails/retry`,{method:'POST',body:'{}'});notify('Изпращането на неуспешните имейли е повторено.');void load()}catch(reason){notify(reason instanceof Error?reason.message:'Имейлите не бяха повторени.','error')}}
  if(busy)return <div className="page-content"><div className="loading-state"><span className="spinner"/> Зареждаме поръчката…</div></div>
  if(error||!order)return <div className="page-content"><div className="table-error">{error||'Поръчката не е намерена.'}<button onClick={()=>void load()}>Опитай отново</button><button onClick={()=>navigate('/admin/orders')}>Към поръчките</button></div></div>
  const next=nextStatus[order.status]
  const hasFailedEmails=order.notifications.some(notification=>notification.status==='failed')
  const shipping=order.addresses.find(address=>address.address_type==='shipping')
  return <div className="page-content order-detail-page">
    <div className="order-detail-heading"><div><button className="outline-button order-back-button" onClick={()=>navigate('/admin/orders')}><ArrowLeft size={16}/> Поръчки</button><div className="eyebrow">АДМИНИСТРАЦИЯ / ПОРЪЧКИ / {order.order_number}</div><h1>{order.order_number}</h1><p>Създадена на {dateLabel(order.created_at)}</p></div><div className="order-detail-primary-actions"><OrderBadge status={order.status} label={statusLabels[order.status]}/>{next&&<button className="primary-button" onClick={()=>void setStatus(next)}><Check size={16}/>{statusLabels[next]}</button>}{['new','confirmed','processing'].includes(order.status)&&order.payment_status!=='paid'&&<button className="outline-button danger-button" onClick={()=>void cancel()}><Ban size={16}/> Анулирай</button>}</div></div>
    <div className="order-detail-layout"><main className="order-detail-main">
      <section className="panel order-detail-card"><div className="order-card-title"><PackageCheck size={18}/><h2>Продукти</h2><span>{order.items.length} реда</span></div><div className="order-detail-items">{order.items.map(item=><article className="order-detail-item" key={item.id}><div className="order-detail-item-copy"><strong>{item.product_name}</strong><span>{item.variant_name?`${item.variant_name} · `:''}{item.sku||'Без SKU'} · {item.quantity} бр. × {formatMoney(item.unit_price,settings.decimalSeparator)}</span>{item.customizations.map(customization=><div className="order-detail-customization" key={customization.id}><span>{customization.field_label}:</span>{customization.field_type==='image'&&customization.file_reference?<a href={`/api/v1/admin/orders/customization-files/${customization.id}`} target="_blank" rel="noreferrer">Преглед на изображението</a>:customization.field_type==='checkbox'?<span>{customization.value_text==='1'?'Да':'Не'}</span>:<span>{customization.value_text||'—'}</span>}{Number(customization.price_adjustment)>0&&<small>+{formatMoney(customization.price_adjustment,settings.decimalSeparator)} за брой</small>}</div>)}</div><strong className="order-item-total">{formatMoney(item.line_total,settings.decimalSeparator)}</strong></article>)}</div><div className="order-summary-totals"><span>Междинна сума</span><strong>{formatMoney(order.subtotal,settings.decimalSeparator)}</strong><span>Отстъпки</span><strong>−{formatMoney(order.discount_total,settings.decimalSeparator)}</strong><span>Доставка · {order.shipping_method}</span><strong>{formatMoney(order.shipping_total,settings.decimalSeparator)}</strong><span>Данък</span><strong>{formatMoney(order.tax_total,settings.decimalSeparator)}</strong><span className="order-grand-label">Общо · EUR</span><strong className="order-grand-total">{formatMoney(order.grand_total,settings.decimalSeparator)}</strong></div></section>
      <section className="panel order-detail-card"><div className="order-card-title"><Clock3 size={18}/><h2>История на статуса</h2></div>{order.history?.length?<ol className="order-history-list">{order.history.map(entry=><li key={entry.id}><span className="order-history-dot"/><div><strong>{entry.previous_status?`${statusLabels[entry.previous_status]} → `:''}{statusLabels[entry.new_status]}</strong><small>{dateLabel(entry.created_at)}{entry.admin_name?` · ${entry.admin_name}`:''}</small>{entry.note&&<p>{entry.note}</p>}</div></li>)}</ol>:<p className="muted-cell">Нямате право да преглеждате историята или няма записани промени.</p>}</section>
      <section className="panel order-detail-card"><div className="order-card-title"><CreditCard size={18}/><h2>История на плащането</h2></div>{order.payment_history?.length?<ol className="order-history-list">{order.payment_history.map(entry=><li key={entry.id}><span className="order-history-dot"/><div><strong>{paymentLabels[entry.previous_payment_status]} → {paymentLabels[entry.new_payment_status]}</strong><small>{dateLabel(entry.created_at)}{entry.admin_name?` · ${entry.admin_name}`:''}</small>{entry.note&&<p>{entry.note}</p>}</div></li>)}</ol>:<p className="muted-cell">Няма промени по плащането.</p>}</section>
      <section className="panel order-detail-card"><div className="order-card-title"><Mail size={18}/><h2>Имейл известия</h2></div>{order.notifications.length===0?<p className="muted-cell">Няма създадени известия.</p>:<div className="order-notification-list">{order.notifications.map(item=><div key={item.event_key}><span>{item.event_key}</span><OrderBadge status={item.status} label={{sent:'Изпратен',pending:'Чака изпращане',sending:'Изпраща се',failed:'Неуспешен'}[item.status]}/><small>{item.sent_at?dateLabel(item.sent_at):`Опити: ${item.attempt_count}`}</small>{item.last_error&&<p>{item.last_error}</p>}</div>)}</div>}{hasFailedEmails&&<button className="outline-button" onClick={()=>void retryEmails()}><RefreshCw size={15}/> Повтори неуспешните</button>}</section>
    </main><aside className="order-detail-side">
      <section className="panel order-detail-card"><div className="order-card-title"><CreditCard size={18}/><h2>Плащане</h2></div><OrderBadge status={order.payment_status} label={paymentLabels[order.payment_status]}/><p>Наложен платеж · EUR</p>{order.payment_status!=='refunded'&&order.status!=='cancelled'&&<button className="outline-button" onClick={()=>void updatePayment()}>{order.payment_status==='unpaid'?'Отбележи като платена':'Отбележи като възстановена'}</button>}</section>
      <section className="panel order-detail-card"><div className="order-card-title"><ShoppingBag size={18}/><h2>Клиент</h2></div><strong>{order.customer_first_name} {order.customer_last_name}</strong><a href={`mailto:${order.customer_email}`}>{order.customer_email}</a><span>{order.customer_phone||'Без телефон'}</span>{order.customer_id&&<Link to={`/admin/customers?dialog=edit&id=${order.customer_id}`}>Отвори клиентския профил</Link>}{order.customer_note&&<div className="order-note-block"><strong>Бележка от клиента</strong><p>{order.customer_note}</p></div>}</section>
      <section className="panel order-detail-card"><div className="order-card-title"><PackageCheck size={18}/><h2>Адрес за доставка</h2></div>{shipping?<><strong>{shipping.recipient_name}</strong><span>{shipping.address_line1}{shipping.address_line2?`, ${shipping.address_line2}`:''}</span><span>{shipping.postal_code?`${shipping.postal_code} `:''}{shipping.city}, {shipping.country_code}</span><span>{shipping.phone||'Без телефон'}</span></>:<p className="muted-cell">Няма адрес за доставка.</p>}</section>
      <section className="panel order-detail-card"><div className="order-card-title"><Clock3 size={18}/><h2>Вътрешна бележка</h2></div><textarea rows={5} value={note} maxLength={4000} onChange={event=>setNote(event.target.value)} placeholder="Само за администрацията…"/><button className="outline-button" disabled={savingNote||note===(order.admin_note||'')} onClick={()=>void saveNote()}>{savingNote?'Запис…':'Запази бележката'}</button></section>
    </aside></div>
  </div>
}
