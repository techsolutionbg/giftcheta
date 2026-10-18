import { api, json } from '../lib/api'
import type { Category, ToastState } from '../types/admin'
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { GripVertical, Pencil, Plus, RotateCcw, Tags, Trash2 } from 'lucide-react'
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { EmptyState, RecordName, StatusBadge } from '../components/CatalogComponents'
import { CategoryEditor } from './CategoryEditor'
import { Breadcrumbs } from '../components/Navigation'
import { SearchField } from '../components/SearchField'
import { TablePanel } from '../components/TablePanel'
import { ActionMenu } from '../components/ActionMenu'
import { useLocation, useNavigate } from 'react-router-dom'
import { useConfirmation } from '../components/UnsavedChanges'
import { SelectionControl } from '../components/SelectionControl'
import { Dropdown } from '../components/Dropdown'
import { DataTable } from '../components/DataTable'
import type * as React from 'react'

export function CategoriesPage({notify}:{notify:(message:string,kind?:ToastState['kind'])=>void}) {
  const location=useLocation()
  const navigate=useNavigate()
  const confirm=useConfirmation()
  const [items,setItems]=useState<Category[]>([])
  const [busy,setBusy]=useState(true)
  const [editor,setEditor]=useState<Category|null|false>(false)
  const [search,setSearch]=useState(()=>new URLSearchParams(location.search).get('search')||'')
  const [isTrash,setIsTrash]=useState(()=>new URLSearchParams(location.search).get('trash')==='only')
  const [selected,setSelected]=useState<number[]>([])
  const createdCategoryId=useRef<number|null>(null)
  const rowRefs=useRef(new Map<number,HTMLTableRowElement>())
  const previousPositions=useRef<Map<number,DOMRect>|null>(null)
  const itemsRef=useRef(items)
  const dragSourceId=useRef<number|null>(null)
  const dragHasChanges=useRef(false)
  const rowRefCallbacks=useRef(new Map<number,(node:HTMLTableRowElement|null)=>void>())

  useLayoutEffect(()=>{
    const first=previousPositions.current
    if(!first)return
    previousPositions.current=null
    for(const [id,node] of rowRefs.current){
      const before=first.get(id);if(!before)continue
      const after=node.getBoundingClientRect();const dx=before.left-after.left;const dy=before.top-after.top
      if(!dx&&!dy)continue
      node.getAnimations().forEach(animation=>animation.cancel())
      node.animate([{transform:`translate(${dx}px, ${dy}px)`},{transform:'translate(0, 0)'}],{duration:170,easing:'cubic-bezier(.2,.75,.25,1)'})
    }
  },[items])

  useEffect(()=>{itemsRef.current=items},[items])

  const load=useCallback(async()=>{setBusy(true);try{const params=new URLSearchParams();if(isTrash)params.set('trash','only');const r=await api<{data:Category[]}>(`/categories${params.size?`?${params}`:''}`);setItems(r.data);setSelected([])}catch(e){notify(e instanceof Error?e.message:'Не успяхме да заредим категориите.','error')}finally{setBusy(false)}},[isTrash,notify])
  useEffect(()=>{void load()},[load])
  useEffect(()=>{
    let active=true
    const params=new URLSearchParams(location.search)
    const dialog=params.get('dialog')
    if(dialog==='create-category'){
      setEditor(current=>current===false?null:current)
      return()=>{active=false}
    }
    if(dialog==='edit-category'){
      const id=Number(params.get('id'))
      if(!Number.isInteger(id)||id<1){setEditor(false);return()=>{active=false}}
      if(editor===null&&createdCategoryId.current===id)return()=>{active=false}
      const row=items.find(category=>category.id===id)
      if(row){setEditor(current=>current&&current.id===id?current:row);return()=>{active=false}}
      void api<{data:Category}>(`/categories/${id}`).then(result=>{if(active)setEditor(result.data)}).catch(()=>{if(active){setEditor(false);notify('Категорията не беше намерена.','error')}})
      return()=>{active=false}
    }
    if(editor!==false)setEditor(false)
    return()=>{active=false}
  },[location.search,items,editor,notify])
  useEffect(()=>{const query=new URLSearchParams(location.search);const nextSearch=query.get('search')||'';const nextTrash=query.get('trash')==='only';setSearch(current=>current===nextSearch?current:nextSearch);setIsTrash(current=>current===nextTrash?current:nextTrash)},[location.search])
  const filtered=useMemo(()=>items.filter(c=>c.name.toLowerCase().includes(search.toLowerCase())),[items,search])
  const remove=useCallback(async(c:Category)=>{if(!(await confirm({title:'Преместване в кошчето?',message:`Категорията „${c.name}“ ще бъде преместена в кошчето и може да бъде възстановена.`,confirmLabel:'Премести в кошчето',danger:true})))return;try{await api(`/categories/${c.id}`,{method:'DELETE',body:'{}'});notify('Категорията е преместена в кошчето.');void load()}catch(e){notify(e instanceof Error?e.message:'Неуспешно преместване.','error')}},[confirm,load,notify])
  const restore=useCallback(async(id:number)=>{try{await api(`/categories/${id}/restore`,{method:'POST',body:'{}'});notify('Категорията е възстановена.');void load()}catch(e){notify(e instanceof Error?e.message:'Грешка при възстановяването.','error')}},[load,notify])
  const permanentlyDelete=useCallback(async(c:Category)=>{if(!(await confirm({title:'Изтриване завинаги?',message:`Категорията „${c.name}“ ще бъде изтрита окончателно. Операцията е възможна само ако няма продукти или подкатегории към нея.`,confirmLabel:'Изтрий завинаги',danger:true})))return;try{await api(`/categories/${c.id}/permanent`,{method:'DELETE',body:'{}'});notify('Категорията е изтрита завинаги.');void load()}catch(e){notify(e instanceof Error?e.message:'Окончателното изтриване не успя.','error')}},[confirm,load,notify])
  const moveWhileDragging=useCallback((sourceId:number,targetId:number,after:boolean)=>{if(search.trim()||sourceId===targetId)return;const current=itemsRef.current;const from=current.findIndex(c=>c.id===sourceId);if(from<0||!current.some(c=>c.id===targetId))return;const ordered=[...current];const [moved]=ordered.splice(from,1);const to=ordered.findIndex(c=>c.id===targetId);const destination=to+(after?1:0);if(destination===from)return;previousPositions.current=new Map([...rowRefs.current].map(([id,node])=>[id,node.getBoundingClientRect()]));ordered.splice(destination,0,moved);itemsRef.current=ordered;dragHasChanges.current=true;setItems(ordered)},[search])
  const persistDraggedOrder=useCallback(async()=>{if(!dragHasChanges.current)return;dragHasChanges.current=false;const ordered=itemsRef.current;try{await Promise.all(ordered.map((category,index)=>api(`/categories/${category.id}`,{method:'PATCH',body:json({sort_order:index+1})})));notify('Редът на категориите е запазен.')}catch(e){notify(e instanceof Error?e.message:'Подреждането не се запази.','error');void load()}},[load,notify])
  const toggleActive=useCallback(async(c:Category)=>{try{await api(`/categories/${c.id}`,{method:'PATCH',body:json({is_active:!c.is_active})});notify(c.is_active?'Категорията е скрита.':'Категорията е активирана.');void load()}catch(e){notify(e instanceof Error?e.message:'Статусът не се промени.','error')}},[load,notify])
  const updateDialogUrl=(dialog:'create-category'|'edit-category'|null,id?:number)=>{const params=new URLSearchParams(location.search);if(dialog){params.set('dialog',dialog);if(id)params.set('id',String(id));else params.delete('id')}else{params.delete('dialog');params.delete('id')}navigate(`${location.pathname}${params.size?`?${params.toString()}`:''}`)}
  const openCreate=()=>{createdCategoryId.current=null;setEditor(null);updateDialogUrl('create-category')}
  const openEdit=(category:Category)=>{setEditor(category);updateDialogUrl('edit-category',category.id)}
  const closeEditor=()=>{setEditor(false);updateDialogUrl(null)}
  const save=async(d:Record<string,unknown>):Promise<number>=>{try{const id=editor&&editor!==null?editor.id:typeof d.id==='number'?d.id:null;if(id){await api(`/categories/${id}`,{method:'PATCH',body:json(d)});notify('Категорията е обновена.');void load();return id}const result=await api<{data:{id:number}}>('/categories',{method:'POST',body:json(d)});createdCategoryId.current=result.data.id;notify('Категорията е създадена.');updateDialogUrl('edit-category',result.data.id);void load();return result.data.id}catch(e){throw e}}
  const runBulk=async(action:'trash'|'restore'|'permanent'|'activate'|'hide')=>{
    if(!selected.length)return
    if(action==='activate'||action==='hide'){
      const activate=action==='activate'
      const title=activate?'Активиране на категории?':'Скриване на категории?'
      const message=activate?`Ще бъдат активирани ${selected.length} избрани категории.`:`Ще бъдат скрити ${selected.length} избрани категории.`
      if(!(await confirm({title,message,confirmLabel:activate?'Активирай категориите':'Скрий категориите'})))return
      const results=await Promise.allSettled(selected.map(id=>api(`/categories/${id}`,{method:'PATCH',body:json({is_active:activate})})))
      const complete=results.filter(result=>result.status==='fulfilled').length;const failed=results.length-complete
      notify(`${activate?'Активирани':'Скрити'} категории: ${complete}${failed?`; неуспешни: ${failed}`:''}.`,failed?'error':'success');void load();return
    }
    const labels={trash:{title:'Преместване в кошчето?',message:`Ще бъдат преместени ${selected.length} избрани категории. Те могат да бъдат възстановени.`,confirmLabel:'Премести в кошчето',path:(id:number)=>`/categories/${id}`,method:'DELETE' as const},restore:{title:'Възстановяване на категории?',message:`Ще бъдат възстановени ${selected.length} избрани категории.`,confirmLabel:'Възстанови категориите',path:(id:number)=>`/categories/${id}/restore`,method:'POST' as const},permanent:{title:'Изтриване завинаги?',message:`${selected.length} избрани категории ще бъдат изтрити окончателно, ако нямат продукти или подкатегории.`,confirmLabel:'Изтрий завинаги',path:(id:number)=>`/categories/${id}/permanent`,method:'DELETE' as const}}[action]
    if(!(await confirm({title:labels.title,message:labels.message,confirmLabel:labels.confirmLabel,danger:true})))return
    const results=await Promise.allSettled(selected.map(id=>api(labels.path(id),{method:labels.method,body:'{}'})))
    const complete=results.filter(result=>result.status==='fulfilled').length;const failed=results.length-complete
    notify(`${action==='trash'?'Преместени':action==='restore'?'Възстановени':'Изтрити завинаги'} категории: ${complete}${failed?`; неуспешни: ${failed}`:''}.`,failed?'error':'success');void load()
  }
  const setTrashFilter=(trash:boolean)=>{setIsTrash(trash);setSelected([])}
  const columns=useMemo(()=>{const c=createColumnHelper<Category>();return [
    c.accessor('name',{header:'Категория',cell:info=>{const category=info.row.original;const position=items.findIndex(item=>item.id===category.id)+1;return <div className="product-cell">{!isTrash&&<><button type="button" className="category-drag-handle" draggable={!search.trim()} disabled={!!search.trim()} title={search.trim()?'Изчистете търсенето, за да пренареждате':'Плъзнете, за да промените позицията'} aria-label={`Плъзнете, за да преместите ${category.name}`} onDragStart={event=>{dragSourceId.current=category.id;dragHasChanges.current=false;event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('application/x-giftcheta-category',String(category.id));const invisibleGhost=document.createElement('canvas');invisibleGhost.width=1;invisibleGhost.height=1;event.dataTransfer.setDragImage(invisibleGhost,0,0);event.currentTarget.closest('tr')?.classList.add('is-dragging')}} onDragEnd={event=>{event.currentTarget.closest('tr')?.classList.remove('is-dragging');dragSourceId.current=null;void persistDraggedOrder()}}><GripVertical size={17}/></button><span className="category-position" aria-label={`Позиция ${position}`}>{position}</span></>}<div><RecordName name={info.getValue()} onClick={!isTrash?()=>openEdit(category):undefined}/><small>{category.slug}</small></div></div>}}),
    c.accessor('product_count',{header:'Продукти',enableSorting:false,cell:info=><span className="muted-cell">{info.getValue()} продукта</span>}),
    c.accessor('is_active',{header:'Статус',enableSorting:false,cell:info=><StatusBadge status={isTrash?'trashed':info.getValue()?'active':'draft'} label={isTrash?'В кошчето':info.getValue()?'Активна':'Скрита'}/>}),
    c.display({id:'actions',header:'Действия',enableSorting:false,cell:info=>{const category=info.row.original;return <ActionMenu label={`Действия за ${category.name}`} items={isTrash?[{label:'Възстанови',icon:RotateCcw,onSelect:()=>void restore(category.id)},{label:'Изтрий завинаги',icon:Trash2,danger:true,onSelect:()=>void permanentlyDelete(category)}]:[{label:'Редактирай',icon:Pencil,onSelect:()=>openEdit(category)},{label:category.is_active?'Скрий категорията':'Активирай категорията',icon:Tags,onSelect:()=>void toggleActive(category)},{label:'Премести в кошчето',icon:Trash2,danger:true,onSelect:()=>void remove(category)}]}/>}})
  ]},[items,isTrash,search,restore,permanentlyDelete,toggleActive,remove,persistDraggedOrder,openEdit])
  const table=useReactTable({data:filtered,columns,getCoreRowModel:getCoreRowModel()})
  const getStableRowRef=(id:number)=>{let callback=rowRefCallbacks.current.get(id);if(!callback){callback=node=>{if(node)rowRefs.current.set(id,node);else rowRefs.current.delete(id)};rowRefCallbacks.current.set(id,callback)}return callback}
  const rowProps=useCallback((category:Category)=>({
    ref:getStableRowRef(category.id),
    onDragOver:(event:React.DragEvent<HTMLTableRowElement>)=>{if(!isTrash&&!search.trim()&&Array.from(event.dataTransfer.types).includes('application/x-giftcheta-category')){event.preventDefault();const sourceId=dragSourceId.current;const bounds=event.currentTarget.getBoundingClientRect();if(sourceId)moveWhileDragging(sourceId,category.id,event.clientY>bounds.top+bounds.height/2)}},
    onDrop:(event:React.DragEvent<HTMLTableRowElement>)=>event.preventDefault(),
  }),[isTrash,search,moveWhileDragging])

  return <div className="page-content catalog-page"><div className="page-heading"><div><div className="eyebrow">КАТАЛОГ / СТРУКТУРА</div><h1>{isTrash?'Кошче':'Категории'}</h1><Breadcrumbs/><p>{isTrash?'Възстановете категория или я изтрийте завинаги.':'Организирайте каталога си в ясна йерархия.'}</p></div>{!isTrash&&<button className="primary-button" onClick={openCreate}><Plus size={17}/> Нова категория</button>}</div>
    <TablePanel className="category-panel" filters={<><SearchField value={search} onChange={e=>setSearch(e.target.value)} placeholder="Търси категория…" ariaLabel="Търсене на категории"/><Dropdown className="filter-dropdown" value={isTrash?'trash':''} ariaLabel="Филтър по кошче" onChange={value=>setTrashFilter(value==='trash')} options={[{value:'',label:'Категории'},{value:'trash',label:'Кошче'}]}/></>} filtersTitle="Филтри на категориите">
      <div className="table-toolbar"><SearchField value={search} onChange={e=>setSearch(e.target.value)} placeholder="Търси категория…" ariaLabel="Търсене на категории"/><Dropdown className="filter-dropdown" value={isTrash?'trash':''} ariaLabel="Филтър по кошче" onChange={value=>setTrashFilter(value==='trash')} options={[{value:'',label:'Категории'},{value:'trash',label:'Кошче'}]}/><span className="toolbar-spacer"/>{selected.length>0&&(isTrash?<><button className="outline-button bulk-action-button" onClick={()=>void runBulk('restore')}>Възстанови ({selected.length})</button><button className="outline-button bulk-action-button danger" onClick={()=>void runBulk('permanent')}>Изтрий завинаги ({selected.length})</button></>:<><button className="outline-button bulk-action-button" onClick={()=>void runBulk('activate')}>Активирай ({selected.length})</button><button className="outline-button bulk-action-button" onClick={()=>void runBulk('hide')}>Скрий ({selected.length})</button><button className="outline-button bulk-action-button danger" onClick={()=>void runBulk('trash')}>Премести в кошчето ({selected.length})</button></>)}<span className="category-count">{filtered.length} резултата</span></div>
      {busy?<div className="loading-state"><span className="spinner"/> Зареждаме категориите…</div>:filtered.length===0?<EmptyState icon={<Tags/>} title={isTrash?'Кошчето е празно':search?'Няма такива категории':'Все още няма категории'} description={isTrash?'Преместените тук категории могат да бъдат възстановени или изтрити завинаги.':search?'Променете търсенето.':'Създайте категории, за да подредите продуктите и да улесните клиентите при намирането им.'} categories={!isTrash} action={!isTrash&&!search&&<button className="primary-button" onClick={openCreate}><Plus size={16}/> Добави категория</button>}/>:<DataTable table={table} leadingHeader={<SelectionControl className="check-col" variant="switch" checked={selected.length===filtered.length&&filtered.length>0} indeterminate={selected.length>0&&selected.length<filtered.length} ariaLabel="Избери всички категории" onChange={checked=>setSelected(checked?filtered.map(c=>c.id):[])}/>} renderLeadingCell={row=><SelectionControl className="check-col" variant="switch" checked={selected.includes(row.original.id)} ariaLabel={`Избери категория ${row.original.name}`} onChange={checked=>setSelected(checked?[...selected,row.original.id]:selected.filter(id=>id!==row.original.id))}/>} getRowLabel={row=>row.original.name} getRowProps={row=>rowProps(row.original)}/>}
    </TablePanel>{editor!==false&&<CategoryEditor category={editor||null} categories={items} onClose={closeEditor} onSave={save}/>}</div>
}
