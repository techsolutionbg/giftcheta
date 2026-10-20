import { useCallback, useEffect, useState } from 'react'
import { ArrowDown, ArrowUp, ImagePlus, Pencil, Plus, Trash2, X } from 'lucide-react'
import { api } from '../lib/api'
import type { ToastState } from '../types/admin'
import { Breadcrumbs } from '../components/Navigation'
import { EmptyState, FormField } from '../components/CatalogComponents'
import { useConfirmation } from '../components/UnsavedChanges'
import { ProductImageUploader, type ProductImage } from '../components/ProductImageUploader'

type Banner = { id:number; title:string; description:string; button_label:string; button_url:string; image_path:string; image_alt:string|null; sort_order:number; is_active:number }
type BannerDraft = { title:string; description:string; button_label:string; button_url:string; image_alt:string; sort_order:string; is_active:boolean; image:File|null }
const blankDraft:BannerDraft={title:'',description:'',button_label:'',button_url:'',image_alt:'',sort_order:'0',is_active:true,image:null}

export function BannersPage({notify}:{notify:(message:string,kind?:ToastState['kind'])=>void}) {
  const [items,setItems]=useState<Banner[]>([])
  const [busy,setBusy]=useState(true)
  const [saving,setSaving]=useState(false)
  const [editing,setEditing]=useState<Banner|null|false>(false)
  const confirm=useConfirmation()

  const load=useCallback(async()=>{setBusy(true);try{const result=await api<{data:Banner[]}>('/banners');setItems(result.data)}catch(error){notify(error instanceof Error?error.message:'Банерите не можаха да бъдат заредени.','error')}finally{setBusy(false)}},[notify])
  useEffect(()=>{void load()},[load])

  const save=async(draft:BannerDraft)=>{
    if(!draft.image&&editing===null){notify('Изберете изображение за банера.','error');return}
    setSaving(true)
    const form=new FormData()
    form.set('title',draft.title);form.set('description',draft.description);form.set('button_label',draft.button_label);form.set('button_url',draft.button_url);form.set('image_alt',draft.image_alt);form.set('sort_order',draft.sort_order);form.set('is_active',draft.is_active?'1':'0');if(draft.image)form.set('image',draft.image)
    try{if(editing&&editing!==null){await api(`/banners/${editing.id}`,{method:'POST',body:form});notify('Банерът е запазен.')}else{await api('/banners',{method:'POST',body:form});notify('Банерът е създаден.')}setEditing(false);void load()}
    catch(error){notify(error instanceof Error?error.message:'Банерът не можа да бъде запазен.','error')}finally{setSaving(false)}
  }

  const remove=async(banner:Banner)=>{if(!(await confirm({title:'Изтриване на банера?',message:`„${banner.title}“ и каченото му изображение ще бъдат изтрити.`,confirmLabel:'Изтрий',danger:true})))return;try{await api(`/banners/${banner.id}`,{method:'DELETE',body:'{}'});notify('Банерът е изтрит.');void load()}catch(error){notify(error instanceof Error?error.message:'Банерът не можа да бъде изтрит.','error')}}
  const move=async(index:number,direction:-1|1)=>{const next=[...items],target=index+direction;if(target<0||target>=next.length)return;[next[index],next[target]]=[next[target],next[index]];setItems(next);try{await api('/banners/reorder',{method:'POST',body:JSON.stringify({ids:next.map(item=>item.id)})})}catch(error){notify(error instanceof Error?error.message:'Подредбата не можа да бъде запазена.','error');void load()}}
  const toggle=async(banner:Banner)=>{const form=new FormData();for(const [key,value] of Object.entries(banner))if(key!=='id'&&key!=='image_path'&&key!=='image_alt')form.set(key,String(key==='is_active'?!Boolean(Number(value)):value));form.set('image_alt',banner.image_alt||'');try{await api(`/banners/${banner.id}`,{method:'POST',body:form});notify(banner.is_active?'Банерът е скрит.':'Банерът е активиран.');void load()}catch(error){notify(error instanceof Error?error.message:'Статусът не можа да бъде променен.','error')}}

  return <div className="page-content banners-page">
    <div className="page-heading"><div><div className="eyebrow">НАЧАЛНА СТРАНИЦА / СЪДЪРЖАНИЕ</div><h1>Банери</h1><Breadcrumbs/><p>Управлявайте предложенията в carousel-а на началната страница.</p></div><button className="primary-button" onClick={()=>setEditing(null)}><Plus size={17}/> Нов банер</button></div>
    <section className="panel banners-panel" aria-label="Начални банери">
      {busy?<div className="loading-state"><span className="spinner"/> Зареждаме банерите…</div>:items.length===0?<EmptyState icon={<ImagePlus/>} title="Все още няма банери" description="Добавете изображение за началната страница. Заглавието, описанието и бутонът са незадължителни." action={<button className="primary-button" onClick={()=>setEditing(null)}><Plus size={16}/> Добави банер</button>}/>:<div className="banner-admin-list">{items.map((banner,index)=><article className="banner-admin-row" key={banner.id}>
        <img src={`/${banner.image_path}`} alt={banner.image_alt||banner.title}/>
        <div className="banner-admin-copy"><div className="banner-admin-title">{banner.title&&<h2>{banner.title}</h2>}<span className={`banner-state ${banner.is_active?'active':'inactive'}`}>{banner.is_active?'Публикуван':'Скрит'}</span></div>{banner.description&&<p>{banner.description}</p>}{banner.button_label&&banner.button_url&&<a href={banner.button_url} target="_blank" rel="noreferrer">{banner.button_label} · {banner.button_url}</a>}</div>
        <div className="banner-admin-actions"><button className="icon-button" title="Премести нагоре" aria-label={`Премести ${banner.title} нагоре`} disabled={index===0} onClick={()=>void move(index,-1)}><ArrowUp size={17}/></button><button className="icon-button" title="Премести надолу" aria-label={`Премести ${banner.title} надолу`} disabled={index===items.length-1} onClick={()=>void move(index,1)}><ArrowDown size={17}/></button><button className="outline-button tiny" onClick={()=>void toggle(banner)}>{banner.is_active?'Скрий':'Публикувай'}</button><button className="icon-button" title="Редакция" aria-label={`Редакция на ${banner.title}`} onClick={()=>setEditing(banner)}><Pencil size={16}/></button><button className="icon-button danger-icon" title="Изтрий" aria-label={`Изтрий ${banner.title}`} onClick={()=>void remove(banner)}><Trash2 size={16}/></button></div>
      </article>)}</div>}
    </section>
    {editing!==false&&<BannerEditor key={editing?.id??'new'} banner={editing||null} saving={saving} onClose={()=>setEditing(false)} onSave={save} onError={message=>notify(message,'error')}/>}
  </div>
}

function BannerEditor({banner,saving,onClose,onSave,onError}:{banner:Banner|null;saving:boolean;onClose:()=>void;onSave:(draft:BannerDraft)=>Promise<void>;onError:(message:string)=>void}){
  const [draft,setDraft]=useState<BannerDraft>(banner?{title:banner.title,description:banner.description,button_label:banner.button_label,button_url:banner.button_url,image_alt:banner.image_alt||'',sort_order:String(banner.sort_order),is_active:Boolean(Number(banner.is_active)),image:null}:blankDraft)
  const [pendingImages,setPendingImages]=useState<File[]>([])
  const [primaryPendingIndex,setPrimaryPendingIndex]=useState(-1)
  const existingImage:ProductImage[]=banner?.image_path&&!pendingImages.length?[{id:banner.id,file_path:banner.image_path,is_primary:true,alt_text:banner.image_alt}]:[]
  const update=(key:keyof BannerDraft,value:string|boolean|File|null)=>setDraft(current=>({...current,[key]:value}))
  return <div className="modal-backdrop" data-state="open" onMouseDown={event=>{if(event.target===event.currentTarget&&!saving)onClose()}}><section className="small-modal banner-editor-modal" role="dialog" aria-modal="true" aria-labelledby="banner-editor-title">
    <header className="editor-header"><div><h2 id="banner-editor-title">{banner?'Редакция на банер':'Нов банер'}</h2><p>Съдържанието се показва в carousel-а на началната страница.</p></div><button type="button" className="icon-button editor-close-button" title="Затвори" aria-label="Затвори" disabled={saving} onClick={onClose}><X size={18}/></button></header>
    <form className="banner-editor-form" onSubmit={event=>{event.preventDefault();void onSave(draft)}}>
      <FormField label="Заглавие" helpText="Незадължително. Показва се върху изображението."><input value={draft.title} maxLength={180} onChange={event=>update('title',event.target.value)}/></FormField>
      <FormField label="Описание" helpText="Незадължително. Кратък допълващ текст под заглавието."><textarea rows={3} value={draft.description} maxLength={500} onChange={event=>update('description',event.target.value)}/></FormField>
      <div className="banner-editor-grid"><FormField label="Текст на бутона" helpText="Незадължително. Надпис на основния бутон."><input value={draft.button_label} maxLength={80} onChange={event=>update('button_label',event.target.value)}/></FormField><FormField label="Адрес на бутона" helpText="Незадължително. Вътрешен адрес като /search или HTTPS адрес."><input value={draft.button_url} maxLength={2048} placeholder="/search" onChange={event=>update('button_url',event.target.value)}/></FormField></div>
      <FormField label="Изображение" required={!banner} helpText="JPEG, PNG или WebP до 10 MB."><ProductImageUploader singleImage singleImageLabel="Изображение на банера" singleImageDescription="Изберете едно изображение за carousel-а на началната страница. JPEG, PNG или WebP до 10 MB." images={existingImage} onImagesChange={()=>{}} pendingFiles={pendingImages} onPendingFilesChange={files=>{setPendingImages(files);update('image',files[0]||null)}} primaryPendingIndex={primaryPendingIndex} onPrimaryPendingIndexChange={setPrimaryPendingIndex} onError={onError}/></FormField>
      <div className="banner-editor-grid"><FormField label="Алтернативен текст" helpText="Кратко описание за екранни четци."><input value={draft.image_alt} maxLength={255} onChange={event=>update('image_alt',event.target.value)}/></FormField><FormField label="Позиция" helpText="По-малкото число се показва по-напред."><input type="number" min="0" value={draft.sort_order} onChange={event=>update('sort_order',event.target.value)}/></FormField></div>
      <label className="banner-active-toggle"><input type="checkbox" checked={draft.is_active} onChange={event=>update('is_active',event.target.checked)}/> Показвай на началната страница</label>
      <footer className="editor-footer"><button type="button" className="outline-button" disabled={saving} onClick={onClose}>Отказ</button><button type="submit" className="primary-button" disabled={saving}>{saving?'Запазване…':banner?'Запази банера':'Създай банера'}</button></footer>
    </form>
  </section></div>
}
