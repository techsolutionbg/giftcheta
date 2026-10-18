import { api, json } from '../lib/api'
import type { Category } from '../types/admin'
import { useRef, useState } from 'react'
import type * as React from 'react'
import { Check, Maximize2, X } from 'lucide-react'
import { FormField } from '../components/CatalogComponents'
import { FieldHelp } from '../components/FieldHelp'
import { SelectionControl } from '../components/SelectionControl'
import { DialogFooter } from '../components/DialogFooter'
import { Dropdown } from '../components/Dropdown'
import { DescriptionEditorDialog } from '../components/DescriptionEditorDialog'
import { LazyRichTextEditor } from '../components/LazyRichTextEditor'
import { ProductImageUploader, uploadCategoryImage, type ProductImage } from '../components/ProductImageUploader'
import { useUnsavedChanges } from '../components/UnsavedChanges'

export function CategoryEditor({category,categories,onClose,onSave}:{category:Category|null;categories:Category[];onClose:()=>void;onSave:(value:Record<string,unknown>)=>Promise<number|void>}) {
  const [closing,setClosing]=useState(false)
  const [descriptionOpen,setDescriptionOpen]=useState(false)
  const [savedId,setSavedId]=useState<number|null>(category?.id??null)
  const [form,setForm]=useState<Record<string,any>>({name:category?.name||'',slug:category?.slug||'',description:category?.description||'',parent_id:category?.parent_id||'',is_active:Boolean(category?.is_active??1),sort_order:category?.sort_order||0})
  const initialForm=useRef<string|null>(null)
  if(initialForm.current===null)initialForm.current=JSON.stringify(form)
  const [images,setImages]=useState<ProductImage[]>(category?.image_path?[{id:category.id,file_path:category.image_path,is_primary:true}]:[])
  const [pendingFiles,setPendingFiles]=useState<File[]>([])
  const [primaryPendingIndex,setPrimaryPendingIndex]=useState(-1)
  const [err,setErr]=useState('')
  const [saving,setSaving]=useState(false)
  const [uploading,setUploading]=useState(false)
  const dirty=JSON.stringify(form)!==initialForm.current||pendingFiles.length>0
  const {confirmDiscard,markClean}=useUnsavedChanges('категория',dirty)
  const existing=Boolean(savedId)

  const close=async()=>{if(closing||saving||uploading||!(await confirmDiscard()))return;setClosing(true);window.setTimeout(onClose,240)}
  const submit=async(event:React.FormEvent)=>{
    event.preventDefault();setSaving(true);setErr('')
    try{
      const id=await onSave({...form,...(savedId?{id:savedId}:{}),parent_id:form.parent_id||null})
      const categoryId=typeof id==='number'?id:savedId
      if(categoryId)setSavedId(categoryId)
      if(categoryId&&pendingFiles.length){
        setUploading(true)
        try{const uploaded=await uploadCategoryImage(categoryId,pendingFiles[0]);setImages([uploaded]);setPendingFiles([]);setPrimaryPendingIndex(-1)}
        catch(error){setErr(error instanceof Error?`Категорията е запазена, но изображението не беше качено: ${error.message}`:'Категорията е запазена, но изображението не беше качено.');return}
        finally{setUploading(false)}
      }
      initialForm.current=JSON.stringify(form);markClean()
    }catch(error){setErr(error instanceof Error?error.message:'Грешка при запис.')}
    finally{setSaving(false)}
  }

  const updateDescription=async(description:string)=>{
    if(savedId)await api(`/categories/${savedId}`,{method:'PATCH',body:json({description})})
    setForm((current:Record<string,any>)=>({...current,description}))
    try{const baseline=JSON.parse(initialForm.current||'{}');initialForm.current=JSON.stringify({...baseline,description})}catch{initialForm.current=JSON.stringify({...form,description})}
  }

  return <div className="modal-backdrop" data-state={closing?'closed':'open'} onMouseDown={event=>{if(event.target===event.currentTarget)void close()}}>
    <div className="small-modal category-editor-modal" data-state={closing?'closed':'open'}>
      <header className="editor-header"><div><h2>{existing?'Редакция на категория':'Създаване на категория'}</h2></div><button type="button" className="icon-button editor-close-button" title="Затвори редактора" aria-label="Затвори редактора" onClick={()=>void close()}><X size={18}/></button></header>
      <form className="category-form" onSubmit={submit}>
        <div className="category-form-grid">
          <FormField label="Име" required helpText="Името на категорията, което ще се показва в каталога."><input value={form.name} onChange={event=>setForm({...form,name:event.target.value})} required maxLength={160}/></FormField>
          <FormField label="URL адрес" helpText="Краткият адрес на категорията. Ако го оставите празен, се създава автоматично от името."><input value={form.slug} onChange={event=>setForm({...form,slug:event.target.value})} placeholder="Автоматично от името"/></FormField>
          <FormField label="Родителска категория" helpText="Изберете категория, под която да бъде подредена тази подкатегория."><Dropdown ariaLabel="Родителска категория" value={String(form.parent_id)} onChange={value=>setForm({...form,parent_id:value})} options={[{value:'',label:'Без родителска категория'},...categories.filter(item=>item.id!==(savedId??category?.id)).map(item=>({value:String(item.id),label:item.name}))]}/></FormField>
          <FormField label="Ред на показване" helpText="Определя позицията на категорията спрямо останалите. По-малките стойности се показват по-рано."><input type="number" value={form.sort_order} onChange={event=>setForm({...form,sort_order:event.target.value})}/></FormField>
          <div className="check-setting-row category-active-row"><SelectionControl variant="switch" className="check-setting" checked={form.is_active} onChange={checked=>setForm({...form,is_active:checked})} label="Активна категория"/><FieldHelp content="Само активните категории се показват в публичния каталог и при избора им към продукт."/></div>
          <div className="category-form-full"><FormField label="Описание" helpText="Подробна информация за съдържанието на категорията."><div className="category-description-field"><LazyRichTextEditor value={form.description||''} onChange={description=>setForm((current:Record<string,any>)=>({...current,description}))} ariaLabel="Описание на категорията" compact/><button type="button" className="outline-button category-description-fullscreen" onClick={()=>setDescriptionOpen(true)}><Maximize2 size={15}/> Редактирай на цял екран</button></div></FormField></div>
          <div className="category-form-full"><FormField label="Изображение" helpText="Качете изображение, което визуално представя категорията."><ProductImageUploader categoryId={savedId??undefined} singleImage images={images} onImagesChange={setImages} pendingFiles={pendingFiles} onPendingFilesChange={setPendingFiles} primaryPendingIndex={primaryPendingIndex} onPrimaryPendingIndexChange={setPrimaryPendingIndex} onError={setErr}/></FormField></div>
          {err&&<div className="form-error category-form-full" role="alert">{err}</div>}
        </div>
        <DialogFooter><button type="button" className="outline-button" onClick={()=>void close()}>Отказ</button><button className="primary-button" disabled={saving||uploading}>{saving?'Запис…':'Запази'}<Check size={15}/></button></DialogFooter>
      </form>
    </div>
    {descriptionOpen&&savedId&&<DescriptionEditorDialog title="Описание на категорията" subject={form.name||category?.name||''} initialValue={form.description||''} onSave={updateDescription} onClose={()=>setDescriptionOpen(false)}/>}
  </div>
}
