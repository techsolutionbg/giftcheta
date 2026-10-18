import { useEffect, useState } from 'react'
import { ImagePlus, LoaderCircle, Star, Trash2, Upload } from 'lucide-react'
import { api, json } from '../lib/api'

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
  images: ProductImage[]
  onImagesChange: (images: ProductImage[]) => void
  pendingFiles: File[]
  onPendingFilesChange: (files: File[]) => void
  primaryPendingIndex: number
  onPrimaryPendingIndexChange: (index: number) => void
  onError: (message: string) => void
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

export function ProductImageUploader({ productId, images, onImagesChange, pendingFiles, onPendingFilesChange, primaryPendingIndex, onPrimaryPendingIndexChange, onError }: ProductImageUploaderProps) {
  const [busy, setBusy] = useState(false)
  const [previews, setPreviews] = useState<string[]>([])

  useEffect(() => {
    const urls = pendingFiles.map(file => URL.createObjectURL(file))
    setPreviews(urls)
    return () => urls.forEach(url => URL.revokeObjectURL(url))
  }, [pendingFiles])

  const selectFiles = async (files: FileList | null) => {
    if (!files?.length) return
    const selected = Array.from(files)
    if (!productId) {
      const offset = pendingFiles.length
      onPendingFilesChange([...pendingFiles, ...selected])
      if (!images.some(image => Boolean(image.is_primary)) && pendingFiles.length === 0) onPrimaryPendingIndexChange(offset)
      return
    }
    setBusy(true)
    try {
      const next = await uploadProductImages(productId, selected, images.length === 0 ? 0 : -1)
      onImagesChange([...images, ...next])
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Качването на изображенията не успя.')
    } finally {
      setBusy(false)
    }
  }

  const setPrimary = async (image: ProductImage) => {
    if (!productId) return
    setBusy(true)
    try {
      await api(`/images/${image.id}`, { method: 'PATCH', body: json({ is_primary: true }) })
      onImagesChange(images.map(item => ({ ...item, is_primary: item.id === image.id })))
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Основното изображение не можа да се промени.')
    } finally {
      setBusy(false)
    }
  }

  const removeImage = async (image: ProductImage) => {
    if (!productId || !window.confirm('Да изтрием изображението?')) return
    setBusy(true)
    try {
      await api(`/images/${image.id}`, { method: 'DELETE', body: '{}' })
      const remaining = images.filter(item => item.id !== image.id)
      if (Boolean(image.is_primary) && remaining[0]) remaining[0] = { ...remaining[0], is_primary: true }
      onImagesChange(remaining)
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Изображението не можа да бъде изтрито.')
    } finally {
      setBusy(false)
    }
  }

  return <section className="product-image-uploader" aria-label="Изображения на продукта">
    <div className="product-image-uploader-heading"><div><h3>Изображения</h3><p>Добавете основно изображение и още снимки за галерията. JPEG, PNG или WebP до 10 MB.</p></div>{busy && <LoaderCircle className="product-image-spinner" size={18} aria-label="Обработваме изображенията"/>}</div>
    <label className={`product-image-dropzone${busy ? ' is-busy' : ''}`}>
      <input type="file" accept="image/jpeg,image/png,image/webp" multiple disabled={busy} onChange={event => { void selectFiles(event.currentTarget.files); event.currentTarget.value = '' }}/>
      <span className="product-image-dropzone-icon">{busy ? <LoaderCircle size={22}/> : <ImagePlus size={22}/>}</span>
      <strong>{busy ? 'Обработваме изображенията…' : 'Изберете изображения'}</strong>
      <span>Можете да добавите няколко файла наведнъж</span>
      <span className="outline-button product-image-select-button"><Upload size={15}/> Избери файлове</span>
    </label>

    {(images.length > 0 || pendingFiles.length > 0) ? <div className="product-image-grid">
      {images.map(image => <article className={`product-image-card${image.is_primary ? ' is-primary' : ''}`} key={`saved-${image.id}`}>
        <img src={`/${image.thumbnail_path || image.file_path}`} alt={image.alt_text || ''}/>
        <div className="product-image-card-actions">{image.is_primary ? <span className="product-image-primary-badge"><Star size={13} fill="currentColor"/> Основно</span> : <button type="button" className="outline-button tiny" disabled={busy} onClick={() => void setPrimary(image)}><Star size={13}/> Задай основно</button>}<button type="button" className="icon-button compact danger-icon" title="Изтрий изображението" aria-label="Изтрий изображението" disabled={busy} onClick={() => void removeImage(image)}><Trash2 size={15}/></button></div>
      </article>)}
      {pendingFiles.map((file, index) => <article className={`product-image-card${index === primaryPendingIndex ? ' is-primary' : ''}`} key={`pending-${file.name}-${file.size}-${index}`}>
        {previews[index] && <img src={previews[index]} alt={file.name}/>}
        <div className="product-image-card-actions">{index === primaryPendingIndex ? <span className="product-image-primary-badge"><Star size={13} fill="currentColor"/> Основно</span> : <button type="button" className="outline-button tiny" onClick={() => onPrimaryPendingIndexChange(index)}><Star size={13}/> Задай основно</button>}<button type="button" className="icon-button compact danger-icon" title="Премахни избраното изображение" aria-label="Премахни избраното изображение" onClick={() => { const next = pendingFiles.filter((_, fileIndex) => fileIndex !== index); onPendingFilesChange(next); if (index === primaryPendingIndex) onPrimaryPendingIndexChange(next.length ? 0 : -1); else if (index < primaryPendingIndex) onPrimaryPendingIndexChange(primaryPendingIndex - 1) }}><Trash2 size={15}/></button></div>
      </article>)}
    </div> : <div className="product-image-empty">Все още няма избрани изображения.</div>}
  </section>
}
