import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { ImagePlus, LoaderCircle, Star, Trash2, Upload, X } from 'lucide-react'
import { api, json } from '../lib/api'
import { useConfirmation } from './UnsavedChanges'

export type ProductImage = {
  id: number
  file_path: string
  thumbnail_path?: string | null
  is_primary: number | boolean
  mime_type?: string
  file_size?: number
  alt_text?: string | null
}

type ProductImageUploaderProps = {
  productId?: number
  categoryId?: number
  singleImage?: boolean
  images: ProductImage[]
  onImagesChange: (images: ProductImage[]) => void
  pendingFiles: File[]
  onPendingFilesChange: (files: File[]) => void
  primaryPendingIndex: number
  onPrimaryPendingIndexChange: (index: number) => void
  onError: (message: string) => void
  ariaLabelledBy?: string
  singleImageLabel?: string
  singleImageDescription?: string
}

type UploadResponse = { data: ProductImage }

export async function uploadProductImages(productId: number, files: File[], primaryIndex: number) {
  const uploaded: ProductImage[] = []
  for (const [index, file] of files.entries()) {
    const body = new FormData()
    body.append('image', file)
    const result = await api<UploadResponse>(`/products/${productId}/images`, { method: 'POST', body })
    const image = result.data
    if (index === primaryIndex) {
      await api(`/images/${image.id}`, { method: 'PATCH', body: json({ is_primary: true }) })
      image.is_primary = true
    }
    uploaded.push(image)
  }
  return uploaded
}

export async function uploadCategoryImage(categoryId: number, file: File): Promise<ProductImage> {
  const body = new FormData()
  body.append('image', file)
  const result = await api<{ data: { image_path: string } }>(`/categories/${categoryId}/image`, { method: 'POST', body })
  return { id: categoryId, file_path: result.data.image_path, is_primary: true }
}

export function ProductImageUploader({ productId, categoryId, singleImage = false, images, onImagesChange, pendingFiles, onPendingFilesChange, primaryPendingIndex, onPrimaryPendingIndexChange, onError, ariaLabelledBy, singleImageLabel, singleImageDescription }: ProductImageUploaderProps) {
  const [busy, setBusy] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [primaryImageId, setPrimaryImageId] = useState<number | null>(null)
  const confirm = useConfirmation()
  const [previews, setPreviews] = useState<string[]>([])
  const [lightbox, setLightbox] = useState<{ src: string; alt: string } | null>(null)

  useEffect(() => {
    const urls = pendingFiles.map(file => URL.createObjectURL(file))
    setPreviews(urls)
    return () => urls.forEach(url => URL.revokeObjectURL(url))
  }, [pendingFiles])

  useEffect(() => {
    if (!lightbox) return
    const onKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape') setLightbox(null) }
    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [lightbox])

  const selectFiles = async (files: FileList | null) => {
    if (!files?.length) return
    const selected = Array.from(files).slice(0, singleImage ? 1 : undefined)
    if (!productId && !categoryId) {
      const offset = pendingFiles.length
      onPendingFilesChange(singleImage ? selected : [...pendingFiles, ...selected])
      if (singleImage || (!images.some(image => Boolean(image.is_primary)) && pendingFiles.length === 0)) onPrimaryPendingIndexChange(offset)
      return
    }
    setBusy(true)
    setUploading(true)
    try {
      const next = categoryId
        ? [await uploadCategoryImage(categoryId, selected[0])]
        : await uploadProductImages(productId!, selected, images.length === 0 ? 0 : -1)
      onImagesChange([...images, ...next])
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Качването на изображенията не успя.')
    } finally {
      setUploading(false)
      setBusy(false)
    }
  }

  const setPrimary = async (image: ProductImage) => {
    if (!productId || categoryId) return
    setBusy(true)
    setPrimaryImageId(image.id)
    try {
      await api(`/images/${image.id}`, { method: 'PATCH', body: json({ is_primary: true }) })
      onImagesChange(images.map(item => ({ ...item, is_primary: item.id === image.id })))
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Основното изображение не можа да се промени.')
    } finally {
      setPrimaryImageId(null)
      setBusy(false)
    }
  }

  const removeImage = async (image: ProductImage) => {
    if ((!productId && !categoryId) || !(await confirm({ title: 'Изтриване на изображение?', message: singleImage ? 'Изображението на категорията ще бъде премахнато.' : 'Това изображение ще бъде премахнато от продукта.', confirmLabel: 'Изтрий изображението', danger: true }))) return
    setBusy(true)
    try {
      if (categoryId) await api(`/categories/${categoryId}/image`, { method: 'DELETE', body: '{}' })
      else await api(`/images/${image.id}`, { method: 'DELETE', body: '{}' })
      const remaining = images.filter(item => item.id !== image.id)
      if (Boolean(image.is_primary) && remaining[0]) remaining[0] = { ...remaining[0], is_primary: true }
      onImagesChange(remaining)
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Изображението не можа да бъде изтрито.')
    } finally {
      setBusy(false)
    }
  }

  const activeLightbox = lightbox

  return <>
  <section className={`product-image-uploader${singleImage ? ' category-image-uploader' : ''}`} aria-labelledby={ariaLabelledBy} aria-label={singleImageLabel || (singleImage ? 'Изображение на категорията' : 'Изображения на продукта')}>
    <div className="product-image-uploader-heading"><div>{!singleImage&&<h3>Изображения</h3>}<p>{singleImage ? 'Добавете едно изображение. JPEG, PNG или WebP до 10 MB.' : 'Добавете основно изображение и още снимки за галерията. JPEG, PNG или WebP до 10 MB.'}</p></div></div>
    <label className={`product-image-dropzone${uploading ? ' is-busy' : ''}`}>
      <input type="file" accept="image/jpeg,image/png,image/webp" multiple={!singleImage} disabled={busy} onChange={event => { void selectFiles(event.currentTarget.files); event.currentTarget.value = ''}}/>
      <span className="product-image-dropzone-icon">{uploading ? <LoaderCircle className="product-image-spinner" size={22}/> : <ImagePlus size={22}/>}</span>
      <strong>{uploading ? 'Обработваме изображенията…' : 'Изберете изображения'}</strong>
      <span>{singleImage ? singleImageDescription || 'Изберете едно изображение за категорията' : 'Можете да добавите няколко файла наведнъж'}</span>
      <span className="outline-button product-image-select-button"><Upload size={15}/> Избери файлове</span>
    </label>

    {(images.length > 0 || pendingFiles.length > 0) ? <div className="product-image-grid">
      {images.map(image => <article className={`product-image-card${image.is_primary ? ' is-primary' : ''}`} key={`saved-${image.id}`}>
        <button type="button" className="product-image-preview" onClick={() => setLightbox({src:`/${image.file_path}`,alt:image.alt_text||(singleImage?'Изображение на категорията':'Изображение на продукта')})} aria-label="Покажи изображението в голям размер"><img src={`/${image.thumbnail_path || image.file_path}`} alt={image.alt_text || ''}/></button>
        <div className="product-image-card-actions">{!singleImage&&(image.is_primary ? <span className="product-image-primary-badge"><Star size={12} fill="currentColor"/> Предно изображение</span> : <button type="button" className="outline-button tiny" disabled={busy} onClick={() => void setPrimary(image)}>{primaryImageId===image.id?<LoaderCircle className="product-image-spinner" size={14}/>:<Star size={13}/>} Задай основно</button>)}{(productId||categoryId)&&<button type="button" className="icon-button compact danger-icon" title="Изтрий изображението" aria-label="Изтрий изображението" disabled={busy} onClick={() => void removeImage(image)}><Trash2 size={15}/></button>}</div>
      </article>)}
      {pendingFiles.map((file, index) => <article className={`product-image-card${index === primaryPendingIndex ? ' is-primary' : ''}`} key={`pending-${file.name}-${file.size}-${index}`}>
        {previews[index] && <button type="button" className="product-image-preview" onClick={() => setLightbox({src:previews[index],alt:file.name})} aria-label="Покажи изображението в голям размер"><img src={previews[index]} alt={file.name}/></button>}
        <div className="product-image-card-actions">{!singleImage&&(index === primaryPendingIndex ? <span className="product-image-primary-badge"><Star size={12} fill="currentColor"/> Предно изображение</span> : <button type="button" className="outline-button tiny" onClick={() => onPrimaryPendingIndexChange(index)}><Star size={13}/> Задай основно</button>)}<button type="button" className="icon-button compact danger-icon" title="Премахни избраното изображение" aria-label="Премахни избраното изображение" onClick={() => { const next = pendingFiles.filter((_, fileIndex) => fileIndex !== index); onPendingFilesChange(next); if (index === primaryPendingIndex) onPrimaryPendingIndexChange(next.length ? 0 : -1); else if (index < primaryPendingIndex) onPrimaryPendingIndexChange(primaryPendingIndex - 1) }}><Trash2 size={15}/></button></div>
      </article>)}
    </div> : <div className="product-image-empty">Все още няма избрани изображения.</div>}
  </section>
  {activeLightbox&&createPortal(<div className="image-lightbox" role="dialog" aria-modal="true" aria-label="Преглед на изображение" onMouseDown={event=>{if(event.target===event.currentTarget)setLightbox(null)}}><button type="button" className="icon-button image-lightbox-close" aria-label="Затвори прегледа" onClick={()=>setLightbox(null)}><X size={20}/></button><img src={activeLightbox.src} alt={activeLightbox.alt}/></div>,document.body)}
  </>
}
